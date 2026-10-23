<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Card;

/**
 * Avança o card para a próxima coluna do quadro por posição (specs/17, BoardColumn::nextColumn()).
 *
 * Usado pelos fluxos públicos que recebem algo do card e o empurram sozinhos na fila — minuta do
 * fornecedor (specs/19) e Solicitação de Nota. Sem usuário logado (o envio é de um link público),
 * então a movimentação fica sem `user_id`. Se o card já estiver na última coluna, não há para onde
 * avançar: fica como está, sem erro — é um efeito automático, não uma ação com feedback de falha
 * para quem só preencheu um formulário.
 *
 * Diferente de `ApproveCard` (specs/17): aquele tem usuário autenticado, recusa (422) quando não há
 * próxima coluna, e devolve o card — comportamento de uma AÇÃO, não de um efeito colateral
 * silencioso. Não compartilha este helper de propósito.
 */
class AdvanceCardToNextColumn
{
    public function execute(Card $card, MovementType $type): void
    {
        // loadMissing explícito: o card chega de origens variadas (relação de um form token, ou já
        // carregado por outro caminho) sem `column`/`board` garantidos, e
        // Model::preventLazyLoading() está ligado fora de produção. `column.board` é o board da
        // COLUNA (para nextColumn() comparar posições dentro do mesmo quadro) — relação separada
        // do `board` do próprio card, usado embaixo para contar a posição na coluna de destino.
        $card->loadMissing(['column.board', 'board']);

        $nextColumn = $card->column->nextColumn();

        if (! $nextColumn) {
            return;
        }

        $fromColumnId = $card->board_column_id;

        $position = (int) $card->board->cards()
            ->where('board_column_id', $nextColumn->id)
            ->max('position') + 1;

        $card->update([
            'board_column_id' => $nextColumn->id,
            'position' => $position,
        ]);

        $card->movements()->create([
            'user_id' => null,
            'from_board_id' => $card->board_id,
            'to_board_id' => $card->board_id,
            'from_column_id' => $fromColumnId,
            'to_column_id' => $nextColumn->id,
            'type' => $type->value,
        ]);
    }
}
