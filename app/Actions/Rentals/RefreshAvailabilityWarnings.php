<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Services\Rentals\RentalAvailabilityService;

/**
 * Recalcula `has_availability_warning` (specs/24 §6) — nunca bloqueia, só sinaliza. Um pedido novo
 * em Confirmada/Em uso pode causar falta em OUTROS pedidos que reservam o mesmo item no período
 * sobreposto, então `execute()` varre todos eles; `refreshOne()` resolve um único pedido.
 */
class RefreshAvailabilityWarnings
{
    public function __construct(private RentalAvailabilityService $availability) {}

    public function execute(?RentalOrder $changedOrder = null): void
    {
        $query = RentalOrder::query()
            ->whereIn('status', [RentalOrderStatus::Confirmada->value, RentalOrderStatus::EmUso->value]);

        if ($changedOrder && $changedOrder->start_at && $changedOrder->end_at) {
            $query->where('start_at', '<', $changedOrder->end_at)
                ->where('end_at', '>', $changedOrder->start_at);
        }

        foreach ($query->with('items')->get() as $order) {
            $this->refreshOne($order);
        }
    }

    public function refreshOne(RentalOrder $order): bool
    {
        if (! $order->status->reservesStock() || ! $order->start_at || ! $order->end_at) {
            if ($order->has_availability_warning) {
                $order->update(['has_availability_warning' => false]);
            }

            return false;
        }

        $order->loadMissing('items');

        $hasWarning = false;
        foreach ($order->items as $line) {
            $available = $this->availability->availableQuantity(
                $line->rental_item_id, $order->start_at, $order->end_at, $order->id
            );
            if ($line->quantity > $available) {
                $hasWarning = true;
                break;
            }
        }

        if ($order->has_availability_warning !== $hasWarning) {
            $order->update(['has_availability_warning' => $hasWarning]);
        }

        return $hasWarning;
    }
}
