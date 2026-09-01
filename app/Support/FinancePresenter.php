<?php

namespace App\Support;

use App\Domain\Enums\FinanceDocumentKind;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use App\Models\FinancePayment;
use App\Models\FinanceRevenue;

/**
 * Shape único das linhas do Financeiro enviadas ao front (specs/23) — mesmo padrão de
 * `CardPresenter`. O autosave da grade responde com a linha já recalculada (colunas geradas),
 * evitando um segundo round-trip só para ler os totais.
 */
class FinancePresenter
{
    public static function costItem(FinanceCostItem $item): array
    {
        $item->loadMissing(['categoria:id,nome', 'fornecedor:id,name', 'authorizer:id,name', 'documents', 'payments']);

        $paid = (float) $item->payments->sum('amount');
        $actual = (float) $item->total_actual;

        return [
            'id' => $item->id,
            'fornecedor_categoria_id' => $item->fornecedor_categoria_id,
            'categoria' => $item->categoria?->nome,
            'description' => $item->description,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'status_variant' => $item->status->badgeVariant(),
            'status_auto' => $item->status_auto,
            'art_status' => $item->art_status->value,
            'art_label' => $item->art_status->label(),
            'fornecedor_id' => $item->fornecedor_id,
            'supplier_name' => $item->supplier_name,
            'supplier_label' => $item->supplierLabel(),
            'authorized_by' => $item->authorized_by,
            'authorized_by_name' => $item->authorized_by_name,
            'authorizer_label' => $item->authorizerLabel(),
            'daily_count' => (float) $item->daily_count,
            'quantity' => (float) $item->quantity,
            'unit_estimated_1' => (float) $item->unit_estimated_1,
            'unit_estimated_2' => $item->unit_estimated_2 === null ? null : (float) $item->unit_estimated_2,
            'unit_actual' => $item->unit_actual === null ? null : (float) $item->unit_actual,
            'total_estimated_1' => (float) $item->total_estimated_1,
            'total_estimated_2' => (float) $item->total_estimated_2,
            'total_actual' => $actual,
            'current_estimate' => $item->currentEstimate(),
            'paid' => $paid,
            'pending' => $actual - $paid,
            'card_id' => $item->card_id,
            'notes' => $item->notes,
            'documents' => self::documentChips($item),
        ];
    }

    /**
     * Os seis "chips" do bloco CONTROLE, sempre na mesma ordem — cinza quando ausente, verde com
     * contador quando presente.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function documentChips(FinanceCostItem $item): array
    {
        $counts = $item->documents->groupBy(fn ($d) => $d->kind->value)->map->count();

        return collect(FinanceDocumentKind::cases())->map(fn (FinanceDocumentKind $kind) => [
            'kind' => $kind->value,
            'label' => $kind->label(),
            'short' => $kind->shortLabel(),
            'icon' => $kind->icon(),
            'count' => (int) ($counts[$kind->value] ?? 0),
        ])->all();
    }

    public static function document(FinanceDocument $document): array
    {
        $document->loadMissing(['attachment:id,card_id,original_name,size', 'uploader:id,name']);

        return [
            'id' => $document->id,
            'kind' => $document->kind->value,
            'kind_label' => $document->kind->label(),
            'name' => $document->displayName(),
            'size' => $document->displaySize(),
            'from_card' => $document->fromCard(),
            'card_id' => $document->attachment?->card_id,
            'uploader' => $document->uploader?->name,
            'created_at' => $document->created_at?->format('d/m/Y H:i'),
            'url' => route('finance.documents.show', $document),
        ];
    }

    public static function payment(FinancePayment $payment): array
    {
        $payment->loadMissing(['source:id,name', 'creator:id,name']);

        return [
            'id' => $payment->id,
            'finance_payment_source_id' => $payment->finance_payment_source_id,
            'source' => $payment->source?->name,
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at?->format('Y-m-d'),
            'paid_at_label' => $payment->paid_at?->format('d/m/Y'),
            'notes' => $payment->notes,
            'creator' => $payment->creator?->name,
        ];
    }

    public static function revenue(FinanceRevenue $revenue): array
    {
        return [
            'id' => $revenue->id,
            'category' => $revenue->category->value,
            'category_label' => $revenue->category->label(),
            'description' => $revenue->description,
            'empresa_id' => $revenue->empresa_id,
            'estimated_value' => (float) $revenue->estimated_value,
            'actual_value' => (float) $revenue->actual_value,
            'received_value' => (float) $revenue->received_value,
            'pending_value' => (float) $revenue->pending_value,
            'finance_payment_source_id' => $revenue->finance_payment_source_id,
            'received_at' => $revenue->received_at?->format('Y-m-d'),
            'notes' => $revenue->notes,
        ];
    }
}
