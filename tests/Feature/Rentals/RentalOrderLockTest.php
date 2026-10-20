<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Domain\Enums\UserRole;

/**
 * Pedido Encerrado/Cancelado trava dados/linhas/custos, mas recebimentos e pagamentos continuam
 * abertos (specs/24 §6/§17).
 */
class RentalOrderLockTest extends RentalTestCase
{
    public function test_pedido_encerrado_recusa_escrita_em_dados_linhas_e_custos(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();
        $order = $this->order($admin, ['client_name' => 'Cliente']);
        $order->update(['status' => RentalOrderStatus::Encerrada->value, 'revenue_actual' => 0]);

        $this->actingAs($admin)
            ->putJson(route('rentals.orders.update', $order), ['location' => 'Novo local'])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.lines.store', $order), ['rental_item_id' => $item->id, 'quantity' => 1])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.costs.store', $order), ['description' => 'Sublocação'])
            ->assertStatus(422);
    }

    public function test_pedido_encerrado_aceita_recebimentos_e_pagamentos(): void
    {
        $admin = $this->user(UserRole::Admin);
        $order = $this->order($admin, ['client_name' => 'Cliente']);
        $order->update(['status' => RentalOrderStatus::Encerrada->value, 'revenue_actual' => 500]);
        $cost = $order->costs()->create(['description' => 'Sublocação', 'estimated_value' => 100, 'actual_value' => 100]);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.receipts.store', $order), ['amount' => '500,00', 'received_at' => now()->toDateString()])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.payments.store', $cost), ['amount' => '100,00', 'paid_at' => now()->toDateString()])
            ->assertCreated();

        $this->assertDatabaseHas('rental_receipts', ['rental_order_id' => $order->id, 'amount' => 500]);
        $this->assertDatabaseHas('rental_cost_payments', ['rental_order_cost_id' => $cost->id, 'amount' => 100]);
    }

    public function test_pedido_cancelado_tambem_trava_dados(): void
    {
        $admin = $this->user(UserRole::Admin);
        $order = $this->order($admin, ['client_name' => 'Cliente']);
        $order->update(['status' => RentalOrderStatus::Cancelada->value, 'cancelled_at' => now(), 'cancel_reason' => 'x']);

        $this->actingAs($admin)
            ->putJson(route('rentals.orders.update', $order), ['location' => 'Novo local'])
            ->assertStatus(422);
    }
}
