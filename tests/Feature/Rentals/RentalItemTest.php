<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Domain\Enums\UserRole;
use App\Models\RentalOrderItem;

/** Inventário da Gestão de Locações (specs/24 §4.1 / §2 Q11-Q22). */
class RentalItemTest extends RentalTestCase
{
    public function test_investimento_e_calculado_pelo_banco(): void
    {
        $item = $this->item(['quantity' => 4, 'unit_cost' => 250]);

        $this->assertEquals(1000, (float) $item->refresh()->total_investment);
    }

    public function test_telas_de_itens_renderizam_para_o_admin(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();

        $this->actingAs($admin)->get(route('rentals.items.index'))->assertOk();
        $this->actingAs($admin)->get(route('rentals.items.create'))->assertOk();
        $this->actingAs($admin)->get(route('rentals.items.edit', $item))->assertOk();
        $this->actingAs($admin)->get(route('rentals.items.show', $item))->assertOk();
    }

    public function test_admin_cadastra_item(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->post(route('rentals.items.store'), [
                'name' => 'Painel de LED 3x2',
                'quantity' => 5,
                'unit_cost' => '1.200,00',
                'rental_price' => '150,00',
            ])
            ->assertRedirect(route('rentals.items.index'));

        $this->assertDatabaseHas('rental_items', ['name' => 'Painel de LED 3x2', 'quantity' => 5]);
    }

    public function test_usuario_sem_checkbox_nao_acessa_o_modulo(): void
    {
        $this->actingAs($this->user(UserRole::Usuario))
            ->get(route('rentals.items.index'))
            ->assertForbidden();
    }

    public function test_usuario_habilitado_nao_cadastra_edita_ou_exclui_item(): void
    {
        $usuario = $this->user(UserRole::Usuario, ['can_access_rentals' => true]);
        $item = $this->item();

        $this->actingAs($usuario)->get(route('rentals.items.create'))->assertForbidden();
        $this->actingAs($usuario)->post(route('rentals.items.store'), ['name' => 'X', 'quantity' => 1, 'unit_cost' => 10])->assertForbidden();
        $this->actingAs($usuario)->get(route('rentals.items.edit', $item))->assertForbidden();
        $this->actingAs($usuario)->put(route('rentals.items.update', $item), ['name' => 'Y', 'quantity' => 1, 'unit_cost' => 10])->assertForbidden();
        $this->actingAs($usuario)
            ->deleteJson(route('rentals.items.destroy', $item), ['deletion_reason' => 'venda'])
            ->assertForbidden();
    }

    public function test_usuario_habilitado_nao_recebe_valor_do_produto_nem_investimento(): void
    {
        $usuario = $this->user(UserRole::Usuario, ['can_access_rentals' => true]);
        $item = $this->item(['unit_cost' => 7777, 'quantity' => 10, 'name' => 'Telão 4x3']);

        $response = $this->actingAs($usuario)->get(route('rentals.items.index'));

        $response->assertOk();
        $response->assertDontSee(number_format((float) $item->unit_cost, 2, ',', '.'));
        $response->assertDontSee(number_format((float) $item->refresh()->total_investment, 2, ',', '.'));

        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('rentals.items.index'))
            ->assertSee(number_format((float) $item->unit_cost, 2, ',', '.'));
    }

    public function test_exclusao_exige_motivo(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();

        $this->actingAs($admin)
            ->deleteJson(route('rentals.items.destroy', $item))
            ->assertStatus(422)
            ->assertJsonValidationErrors('deletion_reason');
    }

    public function test_exclusao_com_motivo_outros_exige_texto(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item();

        $this->actingAs($admin)
            ->deleteJson(route('rentals.items.destroy', $item), ['deletion_reason' => 'outros'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('deletion_reason_other');

        $this->actingAs($admin)
            ->deleteJson(route('rentals.items.destroy', $item), [
                'deletion_reason' => 'outros', 'deletion_reason_other' => 'Emprestado e nunca devolvido',
            ])
            ->assertOk();

        $this->assertSoftDeleted('rental_items', ['id' => $item->id]);
    }

    public function test_exclusao_bloqueada_com_reserva_ativa_e_permitida_sem_ela(): void
    {
        $admin = $this->user(UserRole::Admin);
        $item = $this->item(['quantity' => 5]);

        $orderAtivo = $this->order($admin, [
            'client_name' => 'Cliente', 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2),
        ]);
        RentalOrderItem::create([
            'rental_order_id' => $orderAtivo->id, 'rental_item_id' => $item->id,
            'item_name' => $item->name, 'quantity' => 2, 'unit_price' => 50,
        ]);
        $orderAtivo->update(['status' => RentalOrderStatus::Confirmada->value]);

        $this->actingAs($admin)
            ->deleteJson(route('rentals.items.destroy', $item), ['deletion_reason' => 'venda'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rental_item');

        // Pedido antigo (já encerrado/sem data futura) não bloqueia mais.
        $orderAtivo->update(['status' => RentalOrderStatus::Encerrada->value]);

        $this->actingAs($admin)
            ->deleteJson(route('rentals.items.destroy', $item), ['deletion_reason' => 'venda'])
            ->assertOk();
    }
}
