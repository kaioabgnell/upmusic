<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\AccessModule;
use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrderItem;

/** Máquina de estados do Pedido de Locação (specs/24 §6). */
class RentalOrderStateMachineTest extends RentalTestCase
{
    public function test_confirmar_exige_cliente_periodo_e_item(): void
    {
        $admin = $this->user('admin');
        $order = $this->order($admin);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.move', $order), ['status' => 'confirmada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rental_order');

        $this->assertSame(RentalOrderStatus::Orcamento, $order->refresh()->status);
    }

    public function test_confirmar_com_falta_de_estoque_nao_bloqueia_mas_avisa(): void
    {
        $admin = $this->user('admin');
        $item = $this->item(['quantity' => 1]);

        $order = $this->order($admin, [
            'client_name' => 'Cliente', 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2),
        ]);
        RentalOrderItem::create([
            'rental_order_id' => $order->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => 5, 'unit_price' => 50,
        ]);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.move', $order), ['status' => 'confirmada'])
            ->assertOk();

        $order->refresh();
        $this->assertSame(RentalOrderStatus::Confirmada, $order->status);
        $this->assertTrue($order->has_availability_warning);
        $this->assertDatabaseHas('rental_order_logs', [
            'rental_order_id' => $order->id, 'type' => RentalOrderLogType::AvailabilityWarning->value,
        ]);
    }

    public function test_pular_etapas_roda_os_gates_no_caminho(): void
    {
        $admin = $this->user('admin');
        $item = $this->item();
        $order = $this->order($admin, [
            'client_name' => 'Cliente', 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2),
        ]);
        RentalOrderItem::create([
            'rental_order_id' => $order->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => 2, 'unit_price' => 50,
        ]);

        // Orçamento -> Devolvida direto: passa pelo gate de Confirmada e conclui a devolução total.
        $this->actingAs($admin)
            ->postJson(route('rentals.orders.move', $order), ['status' => 'devolvida'])
            ->assertOk();

        $order->refresh();
        $this->assertSame(RentalOrderStatus::Devolvida, $order->status);
        $this->assertSame(2, $order->items->first()->returned_quantity);
        $this->assertFalse($order->has_return_divergence);
    }

    public function test_voltar_antes_de_devolvida_limpa_a_conferencia(): void
    {
        $admin = $this->user('admin');
        $item = $this->item();
        $order = $this->order($admin, [
            'client_name' => 'Cliente', 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2),
        ]);
        RentalOrderItem::create([
            'rental_order_id' => $order->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => 2, 'unit_price' => 50,
        ]);

        $this->actingAs($admin)->postJson(route('rentals.orders.move', $order), ['status' => 'devolvida'])->assertOk();
        $this->actingAs($admin)->postJson(route('rentals.orders.move', $order->refresh()), ['status' => 'em_uso'])->assertOk();

        $order->refresh();
        $this->assertNull($order->items->first()->returned_quantity);
        $this->assertFalse($order->has_return_divergence);
    }

    public function test_devolucao_com_divergencia_grava_o_aviso(): void
    {
        $admin = $this->user('admin');
        $item = $this->item();
        $order = $this->order($admin, [
            'client_name' => 'Cliente', 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2),
        ]);
        $line = RentalOrderItem::create([
            'rental_order_id' => $order->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => 5, 'unit_price' => 50,
        ]);
        $order->update(['status' => RentalOrderStatus::EmUso->value]);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.return', $order), ['returns' => [$line->id => 3]])
            ->assertOk()
            ->assertJson(['has_divergence' => true]);

        $order->refresh();
        $this->assertTrue($order->has_return_divergence);
        $this->assertSame(RentalOrderStatus::Devolvida, $order->status);
    }

    public function test_encerrar_exige_realizado_em_receita_e_custos(): void
    {
        $admin = $this->user('admin');
        $order = $this->order($admin, ['client_name' => 'Cliente']);
        $order->update(['status' => RentalOrderStatus::Devolvida->value]);
        $order->costs()->create(['description' => 'Sublocação', 'estimated_value' => 100]);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.close', $order), ['revenue_actual' => '500,00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('costs');

        $cost = $order->costs()->first();
        $this->actingAs($admin)
            ->postJson(route('rentals.orders.close', $order), [
                'revenue_actual' => '500,00', 'costs' => [$cost->id => '100,00'],
            ])
            ->assertOk();

        $order->refresh();
        $this->assertSame(RentalOrderStatus::Encerrada, $order->status);
        $this->assertEquals(500, (float) $order->revenue_actual);
        $this->assertEquals(100, (float) $cost->refresh()->actual_value);
    }

    /** specs/28 §3: o módulo Locações inclui encerrar, reabrir e reativar pedidos. */
    public function test_modulo_locacoes_encerra_reabre_e_reativa_pedido(): void
    {
        $admin = $this->user('admin');
        $usuario = $this->user('sem-acesso', modules: [AccessModule::Rentals]);
        $semModulo = $this->user('usuario');

        $order = $this->order($admin, ['client_name' => 'Cliente']);
        $order->update(['status' => RentalOrderStatus::Devolvida->value]);

        $this->actingAs($semModulo)
            ->postJson(route('rentals.orders.close', $order), ['revenue_actual' => '0,00'])
            ->assertForbidden();
        $this->actingAs($usuario)
            ->postJson(route('rentals.orders.close', $order), ['revenue_actual' => '0,00'])
            ->assertOk();

        $this->actingAs($usuario)->postJson(route('rentals.orders.reopen', $order->refresh()), [])->assertOk();
        $this->assertSame(RentalOrderStatus::Devolvida, $order->refresh()->status);

        $order->update(['status' => RentalOrderStatus::Cancelada->value, 'cancelled_at' => now(), 'cancel_reason' => 'teste']);
        $this->actingAs($usuario)->postJson(route('rentals.orders.reactivate', $order), [])->assertOk();
        $this->assertSame(RentalOrderStatus::Orcamento, $order->refresh()->status);
    }

    public function test_cancelar_exige_motivo(): void
    {
        $admin = $this->user('admin');
        $order = $this->order($admin);

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.cancel', $order), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAs($admin)
            ->postJson(route('rentals.orders.cancel', $order), ['reason' => 'Cliente desistiu'])
            ->assertOk();

        $this->assertSame(RentalOrderStatus::Cancelada, $order->refresh()->status);
    }
}
