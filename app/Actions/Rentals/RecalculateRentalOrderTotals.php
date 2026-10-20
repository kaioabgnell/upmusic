<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalDiscountType;
use App\Models\RentalOrder;

/**
 * Mantém `subtotal` e `revenue_estimated` desnormalizados (specs/24 §7.1) — chamada sempre que
 * linhas ou desconto do pedido mudam, dentro da mesma transação da escrita.
 */
class RecalculateRentalOrderTotals
{
    public function execute(RentalOrder $order): RentalOrder
    {
        $order->loadMissing('items');

        $subtotal = (float) $order->items->sum('total');

        $discount = match ($order->discount_type) {
            RentalDiscountType::Valor => min((float) $order->discount_value, $subtotal),
            RentalDiscountType::Percentual => round($subtotal * min((float) $order->discount_value, 100) / 100, 2),
            default => 0.0,
        };

        $order->update([
            'subtotal' => $subtotal,
            'revenue_estimated' => max(0, round($subtotal - $discount, 2)),
        ]);

        return $order->refresh();
    }
}
