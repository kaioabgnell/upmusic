<?php

namespace App\Actions\Rentals;

use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Models\RentalOrderItem;

/**
 * Cria/edita/remove uma linha de item do pedido (specs/24 §4.3 / §8.3) e mantém os totais e o aviso
 * de disponibilidade em dia. `item_name` e `unit_price` são snapshots: só a linha nova lê o item.
 */
class SaveRentalOrderLine
{
    public function __construct(
        private RecalculateRentalOrderTotals $recalc,
        private RefreshAvailabilityWarnings $warnings,
    ) {}

    public function store(RentalOrder $order, array $data): RentalOrderItem
    {
        $item = RentalItem::findOrFail($data['rental_item_id']);

        $line = $order->items()->create([
            'rental_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $data['quantity'],
            'daily_count' => $data['daily_count'] ?? $this->suggestedDailyCount($order),
            'unit_price' => $data['unit_price'] ?? $item->rental_price,
            'position' => ((int) $order->items()->max('position')) + 1,
        ]);

        $this->afterChange($order);

        return $line->refresh();
    }

    public function update(RentalOrderItem $line, array $data): RentalOrderItem
    {
        $order = $line->order;

        $line->update(array_intersect_key($data, array_flip(['quantity', 'daily_count', 'unit_price'])));

        $this->afterChange($order);

        return $line->refresh();
    }

    public function destroy(RentalOrderItem $line): void
    {
        $order = $line->order;
        $line->delete();
        $this->afterChange($order);
    }

    /**
     * Recalcula as diárias de todas as linhas pelo período atual do pedido (specs/24 §2.1) — chamado
     * quando o usuário confirma o SweetAlert exibido ao mudar o período de um pedido com linhas.
     */
    public function recalculateAllDailyCounts(RentalOrder $order): RentalOrder
    {
        $days = $this->suggestedDailyCount($order);
        $order->items()->update(['daily_count' => $days]);

        return $this->recalc->execute($order->refresh());
    }

    private function suggestedDailyCount(RentalOrder $order): int
    {
        if (! $order->start_at || ! $order->end_at) {
            return 1;
        }

        $days = $order->start_at->copy()->startOfDay()->diffInDays($order->end_at->copy()->startOfDay());

        return max(1, (int) ceil($days));
    }

    private function afterChange(RentalOrder $order): void
    {
        $this->recalc->execute($order);
        if ($order->status->reservesStock()) {
            $this->warnings->refreshOne($order->refresh());
        }
    }
}
