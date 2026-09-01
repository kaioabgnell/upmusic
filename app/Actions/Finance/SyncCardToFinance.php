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
 * Três gatilhos chamam esta Action:
 *   1. o botão "Enviar para o Financeiro" no painel do card;
 *   2. a entrada do card num quadro com `boards.feeds_finance` (MoveCard/TransferCard);
 *   3. o CardAttachmentObserver, quando chega anexo novo num card já vinculado.
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
            } elseif ($overrides) {
                $item->update($this->sanitize($overrides));
            }

            $linked = $this->linkAttachments($card, $item, $actor, $attachmentIds, $kindOverrides);

            $this->deriveStatus->execute($item);

            if ($isNew || $linked > 0) {
                $this->registerOnCard($card, $item, $actor, $isNew, $linked);
            }

            return $item->refresh();
        });
    }

    /** Campos pré-preenchidos a partir do card. O financeiro pode editar tudo depois. */
    private function defaultsFromCard(Card $card, int $sheetId): array
    {
        return [
            'finance_sheet_id' => $sheetId,
            'card_id' => $card->id,
            'fornecedor_categoria_id' => $card->fornecedor?->fornecedor_categoria_id,
            'description' => $card->title,
            'fornecedor_id' => $card->fornecedor_id,
            'authorized_by' => $card->assignee_id,
            'daily_count' => 1,
            'quantity' => 1,
            'unit_estimated_1' => (float) ($card->estimated_value ?? 0),
            'unit_actual' => $this->actualFromCard($card),
            'position' => (int) FinanceCostItem::where('finance_sheet_id', $sheetId)->max('position') + 1,
        ];
    }

    /**
     * Realizado sugerido (specs/23 §6.5). O valor negociado do card tem precedência sobre
     * `actual_value` porque é o número que o financeiro efetivamente paga.
     */
    private function actualFromCard(Card $card): ?float
    {
        $value = match ($card->negociado) {
            CardNegociado::ComNota => $card->valor_com_nota,
            CardNegociado::SemNota => $card->valor_sem_nota,
            default => $card->actual_value,
        };

        return $value === null ? null : (float) $value;
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
     * Vincula os anexos do card como documentos de controle. `geral` e `minuta` não têm mapa
     * automático (specs/23 §6.4) e só entram quando o usuário classifica no modal.
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

            $kind = isset($kindOverrides[$attachment->id])
                ? FinanceDocumentKind::tryFrom($kindOverrides[$attachment->id])
                : FinanceDocumentKind::fromAttachmentKind($attachment->kind);

            if (! $kind) {
                continue;
            }

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
