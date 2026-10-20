<?php

namespace App\Support;

use App\Models\RentalCostPayment;
use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Models\RentalOrderCost;
use App\Models\RentalOrderItem;
use App\Models\RentalOrderLog;
use App\Models\RentalReceipt;
use App\Services\Rentals\RentalAvailabilityService;
use App\Services\Rentals\RentalOrderSummaryService;

/** Shape único dos dados da Gestão de Locações enviados ao front (specs/24), igual ao FinancePresenter. */
class RentalPresenter
{
    /** Card enxuto do Kanban (specs/24 §8.2). */
    public static function orderCard(RentalOrder $order): array
    {
        $order->loadMissing('empresa:id,corporate_name,trade_name');

        return [
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status->value,
            'position' => $order->position,
            'client_label' => $order->clientLabel(),
            'location' => $order->location,
            'start_at' => $order->start_at?->format('Y-m-d\TH:i'),
            'end_at' => $order->end_at?->format('Y-m-d\TH:i'),
            'period_label' => self::periodLabel($order),
            'revenue_estimated' => (float) $order->revenue_estimated,
            'has_availability_warning' => $order->has_availability_warning,
            'has_return_divergence' => $order->has_return_divergence,
        ];
    }

    /** Detalhe completo do drawer (specs/24 §8.3), com itens, custos, recebimentos e totais. */
    public static function orderDetail(RentalOrder $order): array
    {
        $order->loadMissing(['empresa:id,corporate_name,trade_name,type', 'items', 'costs.payments', 'receipts', 'closedBy:id,name']);

        $itemIds = $order->items->pluck('rental_item_id')->all();
        $available = ($order->start_at && $order->end_at)
            ? app(RentalAvailabilityService::class)->availableQuantities($itemIds, $order->start_at, $order->end_at, $order->id)
            : [];

        return [
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'is_locked' => $order->isLocked(),
            'empresa_id' => $order->empresa_id,
            'client_name' => $order->client_name,
            'client_label' => $order->clientLabel(),
            'location' => $order->location,
            'start_at' => $order->start_at?->format('Y-m-d\TH:i'),
            'end_at' => $order->end_at?->format('Y-m-d\TH:i'),
            'period_label' => self::periodLabel($order),
            'discount_type' => $order->discount_type?->value,
            'discount_value' => (float) $order->discount_value,
            'subtotal' => (float) $order->subtotal,
            'revenue_estimated' => (float) $order->revenue_estimated,
            'revenue_actual' => $order->revenue_actual === null ? null : (float) $order->revenue_actual,
            'revenue_due_date' => $order->revenue_due_date?->format('Y-m-d'),
            'has_availability_warning' => $order->has_availability_warning,
            'has_return_divergence' => $order->has_return_divergence,
            'notes' => $order->notes,
            'closed_at' => $order->closed_at?->format('d/m/Y H:i'),
            'closed_by' => $order->closedBy?->name,
            'cancel_reason' => $order->cancel_reason,
            'items' => $order->items->map(fn ($l) => self::orderItem($l, $available))->values(),
            'costs' => $order->costs->map(fn ($c) => self::cost($c))->values(),
            'receipts' => $order->receipts->map(fn ($r) => self::receipt($r))->values(),
            'summary' => app(RentalOrderSummaryService::class)->summary($order),
        ];
    }

    public static function orderItem(RentalOrderItem $line, array $available = []): array
    {
        return [
            'id' => $line->id,
            'rental_item_id' => $line->rental_item_id,
            'item_name' => $line->item_name,
            'quantity' => $line->quantity,
            'daily_count' => $line->daily_count,
            'unit_price' => (float) $line->unit_price,
            'total' => (float) $line->total,
            'returned_quantity' => $line->returned_quantity,
            'available_excluding_self' => $available[$line->rental_item_id] ?? null,
        ];
    }

    public static function cost(RentalOrderCost $cost): array
    {
        $paid = (float) $cost->payments->sum('amount');
        $actual = $cost->actual_value;

        return [
            'id' => $cost->id,
            'rental_order_item_id' => $cost->rental_order_item_id,
            'description' => $cost->description,
            'estimated_value' => (float) $cost->estimated_value,
            'actual_value' => $actual === null ? null : (float) $actual,
            'due_date' => $cost->due_date?->format('Y-m-d'),
            'notes' => $cost->notes,
            'paid' => $paid,
            'pending' => (float) ($actual ?? $cost->estimated_value) - $paid,
            'payments' => $cost->payments->map(fn ($p) => self::costPayment($p))->values(),
        ];
    }

    public static function costPayment(RentalCostPayment $payment): array
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

    public static function receipt(RentalReceipt $receipt): array
    {
        $receipt->loadMissing(['source:id,name', 'creator:id,name']);

        return [
            'id' => $receipt->id,
            'finance_payment_source_id' => $receipt->finance_payment_source_id,
            'source' => $receipt->source?->name,
            'amount' => (float) $receipt->amount,
            'received_at' => $receipt->received_at?->format('Y-m-d'),
            'received_at_label' => $receipt->received_at?->format('d/m/Y'),
            'notes' => $receipt->notes,
            'creator' => $receipt->creator?->name,
        ];
    }

    public static function log(RentalOrderLog $log): array
    {
        $log->loadMissing('user:id,name');

        return [
            'id' => $log->id,
            'type' => $log->type->value,
            'type_label' => $log->type->label(),
            'icon' => $log->type->icon(),
            'from_status' => $log->from_status?->label(),
            'to_status' => $log->to_status?->label(),
            'message' => $log->message,
            'payload' => $log->payload,
            'user' => $log->user?->name,
            'created_at' => $log->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function item(RentalItem $item, bool $withCost = false): array
    {
        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'rental_price' => (float) $item->rental_price,
            'active' => $item->active,
            'notes' => $item->notes,
        ];

        if ($withCost) {
            $data['unit_cost'] = (float) $item->unit_cost;
            $data['total_investment'] = (float) $item->total_investment;
            $data['purchase_date'] = $item->purchase_date?->format('Y-m-d');
        }

        return $data;
    }

    private static function periodLabel(RentalOrder $order): ?string
    {
        if (! $order->start_at || ! $order->end_at) {
            return null;
        }

        return $order->start_at->format('d/m H:i').' — '.$order->end_at->format('d/m H:i');
    }
}
