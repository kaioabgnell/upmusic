<?php

namespace App\Actions\Finance;

use App\Domain\Enums\CardNegociado;
use App\Domain\Enums\FinanceDocumentKind;
use App\Domain\Enums\NotificationType;
use App\Models\Card;
use App\Models\FinanceCostItem;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Finance\FinanceSheetProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A ponte Kanban -> Financeiro (specs/23 §6): leva o card e os anexos dele para a linha de custo do
 * evento, acabando com o "subir tudo no card e subir tudo de novo na planilha".
 *
 * Quatro gatilhos chamam esta Action:
 *   1. o botão "Enviar para o Financeiro" no painel do card;
 *   2. a entrada do card num quadro com `boards.feeds_finance` (MoveCard/TransferCard);
 *   3. o CardAttachmentObserver, quando chega anexo novo num card já vinculado;
 *   4. o CardObserver, quando um card JÁ vinculado tem título/fornecedor/valores alterados —
 *      mantém a linha existente espelhando o card sem precisar reabrir o modal manualmente.
 *
 * É IDEMPOTENTE: rodar de novo no mesmo card não duplica linha nem documento — reusa a linha
 * existente e só vincula o que apareceu depois. Os arquivos NÃO são copiados: `finance_documents`
 * aponta para o `card_attachments` que já existe.
 */
class SyncCardToFinance
{
    public function __construct(
        private FinanceSheetProvider $sheets,
        private CreateFinanceDocument $documents,
        private DeriveCostItemStatus $deriveStatus,
    ) {}

    /**
     * @param  array<string,mixed>  $overrides  campos confirmados no modal (categoria, descrição, valores)
     * @param  array<int>|null  $attachmentIds  anexos escolhidos; null = todos os mapeáveis
     * @param  array<int,string>  $kindOverrides  attachment_id => FinanceDocumentKind::value (classificação manual)
     */
    public function execute(
        Card $card,
        ?User $actor = null,
        array $overrides = [],
        ?array $attachmentIds = null,
        array $kindOverrides = [],
    ): FinanceCostItem {
        $card->loadMissing(['event', 'fornecedor', 'attachments']);

        if (! $card->event) {
            throw ValidationException::withMessages([
                'event_id' => 'Vincule o card a um evento antes de enviar ao Financeiro.',
            ]);
        }

        $sheet = $this->sheets->forEvent($card->event);

        if ($sheet->isClosed()) {
            throw ValidationException::withMessages([
                'finance' => 'A prestação de contas deste evento está fechada.',
            ]);
        }

        return DB::transaction(function () use ($card, $actor, $overrides, $attachmentIds, $kindOverrides, $sheet) {
            $item = FinanceCostItem::where('card_id', $card->id)
                ->where('finance_sheet_id', $sheet->id)
                ->first();

            $isNew = $item === null;

            if ($isNew) {
                // Overrides à esquerda: o que o usuário confirmou no modal vence o sugerido.
                // refresh() logo após o create porque `status`, `status_auto` e os totais são
                // preenchidos pelo banco (default/coluna gerada) e não voltam no model recém-criado.
                $item = $sheet->costItems()->create(
                    $this->sanitize($overrides) + $this->defaultsFromCard($card, $sheet->id)
                )->refresh();
            } else {
                // Linha já existe: sempre reespelha do card (título, fornecedor, valores) — é
                // assim que uma edição no card, sem ninguém reabrir o modal, chega no Financeiro.
                // Overrides à esquerda continuam vencendo o espelhado, para o que a pessoa
                // confirmou/corrigiu no modal não ser pisado pelo valor bruto do card.
                $item->update($this->sanitize($overrides) + $this->mirrorFromCard($card, $item));
            }

            $linked = $this->linkAttachments($card, $item, $actor, $attachmentIds, $kindOverrides);

            $this->deriveStatus->execute($item);

            if ($isNew || $linked > 0) {
                $this->registerOnCard($card, $item, $actor, $isNew, $linked);
            }

            return $item->refresh();
        });
    }

    /**
     * Campos pré-preenchidos a partir do card. O financeiro pode editar tudo depois.
     *
     * `quantity` vem do card só aqui (criação): depois disso, quantidade e diárias passam a ser do
     * financeiro — é ele quem desdobra "1 contrato" em "20 diárias × 3 equipes" na grade, e uma
     * edição no card não pode desfazer isso (specs/23 §3: o card sugere, o financeiro decide).
     */
    private function defaultsFromCard(Card $card, int $sheetId): array
    {
        return $this->mirrorFromCard($card) + [
            'finance_sheet_id' => $sheetId,
            'card_id' => $card->id,
            'authorized_by' => $card->assignee_id,
            'daily_count' => 1,
            'quantity' => (float) ($card->quantity ?? 1),
            'position' => (int) FinanceCostItem::where('finance_sheet_id', $sheetId)->max('position') + 1,
        ];
    }

    /**
     * Campos que a linha do Financeiro sempre espelha do card, na criação e em toda resincronia
     * (specs/23 §6.6) — título, fornecedor (e a categoria dele) e os valores previsto/realizado.
     * `unit_estimated_2` (Vlr. unit. 2/"refinado") fica de fora de propósito: é campo só do
     * Financeiro, sem equivalente no card.
     */
    private function mirrorFromCard(Card $card, ?FinanceCostItem $item = null): array
    {
        return array_filter([
            'description' => $card->title,
            'fornecedor_id' => $card->fornecedor_id,
            'fornecedor_categoria_id' => $card->fornecedor?->fornecedor_categoria_id,
            'unit_estimated_1' => $this->unitEstimatedFor($card),
            'unit_actual' => $this->unitActualFor($card, $item),
        ], fn ($v) => $v !== null);
    }

    /**
     * Unitário PREVISTO a partir do card.
     *
     * "Valor previsto com/sem nota" é o TOTAL do orçamento — é assim que se negocia com o
     * fornecedor ("o serviço sai por 420"), e é o que a pessoa digita ali. A coluna do Financeiro é
     * POR UNIDADE e o total dela é gerado (`unitário × quantidade × diárias`), então mandar o total
     * direto multiplicaria a despesa pela quantidade: 420 num card de 2 diárias virava R$ 840.
     * A conversão mora aqui, dividindo pela quantidade do próprio card.
     *
     * O "Banco de Preços" (`estimated_value`) é a exceção: já é um preço por unidade (vem da média
     * do histórico), então entra sem divisão. Ele é o último recurso, para o card que não tem
     * nenhum valor negociado preenchido.
     */
    public function unitEstimatedFor(Card $card): ?float
    {
        $total = match ($card->negociado) {
            CardNegociado::ComNota => $card->valor_com_nota,
            CardNegociado::SemNota => $card->valor_sem_nota,
            default => $card->valor_com_nota ?? $card->valor_sem_nota,
        };

        if ($total === null) {
            return $card->estimated_value === null ? null : (float) $card->estimated_value;
        }

        $quantity = (float) ($card->quantity ?? 1);

        return $quantity > 0 ? round((float) $total / $quantity, 2) : (float) $total;
    }

    /**
     * Unitário REALIZADO: o "Valor unitário" do card — o preço por unidade que o fornecedor
     * cobrou de fato, que é o número comparável entre eventos (specs/15).
     *
     * O card antigo não tem unitário, só o TOTAL. Copiar esse total para a coluna unitária é o que
     * sempre foi feito, e continua valendo enquanto a linha for 1 × 1 (aí total e unitário são o
     * mesmo número). Se o financeiro já desdobrou a linha em diárias/quantidade, reescrever o
     * unitário com um total multiplicaria a despesa por esse fator — então nesse caso não se mexe
     * no valor que o financeiro ajustou.
     */
    public function unitActualFor(Card $card, ?FinanceCostItem $item = null): ?float
    {
        if ($card->unit_value !== null) {
            return (float) $card->unit_value;
        }

        $total = match ($card->negociado) {
            CardNegociado::ComNota => $card->valor_com_nota,
            CardNegociado::SemNota => $card->valor_sem_nota,
            default => $card->actual_value,
        };

        if ($total === null) {
            return null;
        }

        $multiplier = $item === null ? 1.0 : (float) $item->quantity * (float) $item->daily_count;

        return $multiplier === 1.0 ? (float) $total : null;
    }

    /** Só os campos que o modal pode confirmar; nada de mass assignment cego do request. */
    private function sanitize(array $overrides): array
    {
        return array_filter(
            array_intersect_key($overrides, array_flip([
                'fornecedor_categoria_id', 'description', 'fornecedor_id', 'supplier_name',
                'authorized_by', 'authorized_by_name', 'daily_count', 'quantity',
                'unit_estimated_1', 'unit_estimated_2', 'unit_actual', 'notes',
            ])),
            fn ($v) => $v !== null,
        );
    }

    /**
     * Vincula os anexos do card como documentos de controle, cada um com o MESMO tipo escolhido
     * na hora de anexar (specs/23 §6.4). `$kindOverrides` só existe para o caso de alguém corrigir
     * a classificação no modal antes de enviar.
     *
     * @return int quantos documentos NOVOS foram criados
     */
    private function linkAttachments(
        Card $card,
        FinanceCostItem $item,
        ?User $actor,
        ?array $attachmentIds,
        array $kindOverrides,
    ): int {
        $before = $item->documents()->count();

        foreach ($card->attachments as $attachment) {
            if ($attachmentIds !== null && ! in_array($attachment->id, $attachmentIds, false)) {
                continue;
            }

            $kind = (isset($kindOverrides[$attachment->id])
                ? FinanceDocumentKind::tryFrom($kindOverrides[$attachment->id])
                : null) ?? FinanceDocumentKind::fromAttachmentKind($attachment->kind);

            $this->documents->fromAttachment($item, $attachment, $kind, $actor);
        }

        return $item->documents()->count() - $before;
    }

    /**
     * Deixa rastro do envio no card: comentário no histórico + notificação para o responsável
     * (specs/22). Sem isso, quem trabalha no Kanban não saberia que a linha já existe lá.
     */
    private function registerOnCard(Card $card, FinanceCostItem $item, ?User $actor, bool $isNew, int $linked): void
    {
        $eventName = $card->event?->name;
        $verb = $isNew ? 'Enviado ao Financeiro' : 'Sincronizado com o Financeiro';
        $docs = $linked > 0 ? " {$linked} documento(s) vinculado(s)." : '';

        $card->comments()->create([
            'user_id' => $actor?->id,
            'body' => "{$verb} — linha #{$item->id} do evento {$eventName}.{$docs}",
        ]);

        if (! $isNew || ! $card->assignee_id || $card->assignee_id === $actor?->id) {
            return;
        }

        UserNotification::create([
            'user_id' => $card->assignee_id,
            'actor_id' => $actor?->id,
            'card_id' => $card->id,
            'board_id' => $card->board_id,
            'type' => NotificationType::CardSentToFinance,
            'data' => ['card_title' => $card->title, 'actor_name' => $actor?->name],
        ]);
    }
}
