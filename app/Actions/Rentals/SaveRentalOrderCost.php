<?php

namespace App\Actions\Rentals;

use App\Models\RentalOrder;
use App\Models\RentalOrderCost;

/** CRUD do custo simples do pedido (specs/24 §4.5 / §2 Q21-Q27) — sem efeito nos totais do pedido. */
class SaveRentalOrderCost
{
    public function store(RentalOrder $order, array $data): RentalOrderCost
    {
        return $order->costs()->create([
            'rental_order_item_id' => $data['rental_order_item_id'] ?? null,
            'description' => $data['description'],
            'estimated_value' => $data['estimated_value'] ?? 0,
            'actual_value' => $data['actual_value'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'position' => ((int) $order->costs()->max('position')) + 1,
        ]);
    }

    public function update(RentalOrderCost $cost, array $data): RentalOrderCost
    {
        $cost->update(array_intersect_key($data, array_flip([
            'rental_order_item_id', 'description', 'estimated_value', 'actual_value', 'due_date', 'notes',
        ])));

        return $cost->refresh();
    }

    public function destroy(RentalOrderCost $cost): void
    {
        $cost->delete();
    }
}
