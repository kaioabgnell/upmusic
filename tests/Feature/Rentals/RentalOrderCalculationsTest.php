<?php

namespace Tests\Feature\Rentals;

use App\Actions\Rentals\SaveRentalOrderLine;
use App\Domain\Enums\UserRole;
use App\Models\RentalOrder;
use App\Services\Rentals\RentalOrderSummaryService;

/** Cálculos do pedido de locação (specs/24 §7.1/§7.2). */
class RentalOrderCalculationsTest extends RentalTestCase
{
    public function test_total_da_linha_e_quantidade_vezes_diarias_vezes_unitario(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();
        $order = $this->order($admin);

        $line = app(SaveRentalOrderLine::class)->store($order, [
            'rental_item_id' => $item->id, 'quantity' => 3, 'daily_count' => 2, 'unit_price' => 50,
        ]);

        $this->assertEquals(300, (float) $line->refresh()->total);
        $this->assertEquals(300, (float) $order->refresh()->subtotal);
    }

    public function test_desconto_em_valor_e_limitado_ao_subtotal(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();
        $order = $this->order($admin);
        app(SaveRentalOrderLine::class)->store($order, ['rental_item_id' => $item->id, 'quantity' => 1, 'daily_count' => 1, 'unit_price' => 100]);

        $this->actingAs($admin)->putJson(route('rentals.orders.update', $order), [
            'discount_type' => 'valor', 'discount_value' => '500,00',
        ])->assertOk();

        $this->assertEquals(0, (float) $order->refresh()->revenue_estimated);
    }

    public function test_desconto_percentual(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();
        $order = $this->order($admin);
        app(SaveRentalOrderLine::class)->store($order, ['rental_item_id' => $item->id, 'quantity' => 1, 'daily_count' => 1, 'unit_price' => 200]);

        $this->actingAs($admin)->putJson(route('rentals.orders.update', $order), [
            'discount_type' => 'percentual', 'discount_value' => '10',
        ])->assertOk();

        $this->assertEquals(180, (float) $order->refresh()->revenue_estimated);
    }

    public function test_a_receber_falta_pagar_e_resultado(): void
    {
        $admin = $this->user(UserRole::Admin);
        $order = $this->order($admin);
        $order->update(['revenue_estimated' => 1000, 'revenue_actual' => 1000]);
        $order->receipts()->create(['amount' => 400, 'received_at' => now()]);
        $cost = $order->costs()->create(['description' => 'Sublocação', 'estimated_value' => 200, 'actual_value' => 200]);
        $cost->payments()->create(['amount' => 50, 'paid_at' => now()]);

        $summary = app(RentalOrderSummaryService::class)->summary($order->fresh());

        $this->assertEquals(600, $summary['revenue']['receivable']);
        $this->assertEquals(150, $summary['costs']['pending']);
        $this->assertEquals(800, $summary['result']['estimated']);
    }

    public function test_rodape_da_lista_bate_com_a_soma_da_query_filtrada(): void
    {
        $admin = $this->user(UserRole::Admin);
        $a = $this->order($admin, ['client_name' => 'Cliente A']);
        $a->update(['revenue_estimated' => 500]);
        $b = $this->order($admin, ['client_name' => 'Cliente B']);
        $b->update(['revenue_estimated' => 300]);
        $b->receipts()->create(['amount' => 100, 'received_at' => now()]);

        $totals = app(RentalOrderSummaryService::class)->listTotals(RentalOrder::query());

        $this->assertEquals(800, $totals['revenue_estimated']);
        $this->assertEquals(100, $totals['received']);
        $this->assertEquals(2, $totals['count']);
    }
}
