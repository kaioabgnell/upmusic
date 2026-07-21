<?php

namespace App\Actions\Cards;

use App\Domain\Enums\MovementType;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveCard
{
    /**
     * Aprova o card na coluna atual (que exige aprovação — ver specs/17) e o move para a próxima
     * coluna do quadro por posição. Não há seleção de coluna de destino: aprovar sempre avança
     * para a etapa seguinte configurada.
     */
    public function execute(Card $card, User $actor): Card
    {
        $nextColumn = $card->column->nextColumn();

        abort_if(! $nextColumn, 422, 'Não há próxima etapa configurada para esta coluna.');

        return DB::transaction(function () use ($card, $nextColumn, $actor) {
            $fromColumnId = $card->board_column_id;

            $position = (int) $card->board->cards()
                ->where('board_column_id', $nextColumn->id)
                ->max('position') + 1;

            $card->update([
                'board_column_id' => $nextColumn->id,
                'position' => $position,
            ]);

            $card->movements()->create([
                'user_id' => $actor->id,
                'from_board_id' => $card->board_id,
                'to_board_id' => $card->board_id,
                'from_column_id' => $fromColumnId,
                'to_column_id' => $nextColumn->id,
                'type' => MovementType::Approval->value,
            ]);

            return $card->fresh();
        });
    }
}
