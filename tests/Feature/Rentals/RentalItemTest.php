<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\AccessModule;
use App\Domain\Enums\RentalOrderStatus;
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
        $admin = $this->user('admin');
        $item = $this->item();

        $this->actingAs($admin)->get(route('rentals.items.index'))->assertOk();
        $this->actingAs($admin)->get(route('rentals.items.create'))->assertOk();
        $this->actingAs($admin)->get(route('rentals.items.edit', $item))->assertOk();
        $this->actingAs($admin)->get(route('rentals.items.show', $item))->assertOk();
    }

    public function test_admin_cadastra_item(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('rentals.items.store'), [
                'name' => 'Painel de LED 3x2',
                'quantity' => 5,
                'unit_cost' => '1.200,00',
                'rental_price' => '150,00',
            ])
            ->assertRedirect(route('rentals.items.index'));

        $this->assertDatabaseHas('rental_items', ['name' => 'Painel de LED 3x2', 'quantity' => 5]);
    }

    public function test_sem_o_modulo_locacoes_nao_acessa(): void
    {
        $this->actingAs($this->user('usuario'))
            ->get(route('rentals.items.index'))
            ->assertForbidden();
    }

    /** specs/28 §3: o módulo Locações libera o módulo inteiro, inclusive o inventário. */
    public function test_modulo_locacoes_libera_cadastrar_editar_e_excluir_item(): void
    {
        $usuario = $this->user('sem-acesso', modules: [AccessModule::Rentals]);
        $item = $this->item();

        $this->actingAs($usuario)->get(route('rentals.items.create'))->assertOk();
        $this->actingAs($usuario)->post(route('rentals.items.store'), [
            'name' => 'Painel de LED 3x2', 'quantity' => 5, 'unit_cost' => '1.200,00', 'rental_price' => '150,00',
        ])->assertRedirect(route('rentals.items.index'));
        $this->actingAs($usuario)->get(route('rentals.items.edit', $item))->assertOk();
        $this->actingAs($usuario)
            ->deleteJson(route('rentals.items.destroy', $item), ['deletion_reason' => 'venda'])
            ->assertOk();

        $this->assertDatabaseHas('rental_items', ['name' => 'Painel de LED 3x2']);
        $this->assertSoftDeleted('rental_items', ['id' => $item->id]);
    }

    public function test_modulo_locacoes_ve_valor_do_produto_e_investimento(): void
    {
        $usuario = $this->user('sem-acesso', modules: [AccessModule::Rentals]);
        $item = $this->item(['unit_cost' => 7777, 'quantity' => 10, 'name' => 'Telão 4x3']);

        $this->actingAs($usuario)->get(route('rentals.items.index'))
            ->assertOk()
            ->assertSee(number_format((float) $item->unit_cost, 2, ',', '.'));
    }

    public function test_admin_ve_valor_do_produto(): void
    {
        $item = $this->item(['unit_cost' => 7777, 'quantity' => 10, 'name' => 'Telão 4x3']);

        $this->actingAs($this->user('admin'))
            ->get(route('rentals.items.index'))
            ->assertSee(number_format((float) $item->unit_cost, 2, ',', '.'));
    }

    public function test_exclusao_exige_motivo(): void
    {
        $admin = $this->user('admin');
        $item = $this->item();

        $this->actingAs($admin)
            ->deleteJson(route('rentals.items.destroy', $item))
            ->assertStatus(422)
            ->assertJsonValidationErrors('deletion_reason');
    }

    public function test_exclusao_com_motivo_outros_exige_texto(): void
    {
        $admin = $this->user('admin');
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
        $admin = $this->user('admin');
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
