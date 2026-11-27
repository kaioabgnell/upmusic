<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\AccessModule;

/** Acesso ao módulo (specs/24 §11, alterado pela specs/28: módulo Locações do perfil). */
class RentalAccessTest extends RentalTestCase
{
    public function test_admin_e_perfil_com_locacoes_entram(): void
    {
        $this->actingAs($this->user('admin'))->get(route('rentals.board'))->assertOk();
        $this->actingAs($this->user('coordenador'))->get(route('rentals.board'))->assertOk();
    }

    /** specs/28 §6: Locações não é ligada a evento — a restrição por evento não tira o módulo. */
    public function test_restrito_por_evento_com_o_modulo_acessa(): void
    {
        $user = $this->user('sem-acesso', modules: [AccessModule::Rentals]);
        $event = \App\Models\Event::create([
            'name' => 'Evento X', 'location' => 'Goiânia', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'active' => true,
        ]);
        $user->events()->attach($event->id);

        $this->actingAs($user->fresh())->get(route('rentals.board'))->assertOk();
    }

    public function test_sem_o_modulo_nao_acessa_e_com_o_modulo_acessa(): void
    {
        $this->actingAs($this->user('usuario'))
            ->get(route('rentals.board'))
            ->assertForbidden();

        $this->actingAs($this->user('usuario', modules: [AccessModule::Rentals]))
            ->get(route('rentals.board'))
            ->assertOk();
    }

    public function test_lista_de_pedidos_renderiza(): void
    {
        $admin = $this->user('admin');
        $this->order($admin, ['client_name' => 'Cliente']);

        $this->actingAs($admin)->get(route('rentals.orders.index'))->assertOk()->assertSee('LOC-');
    }

    public function test_codigo_do_pedido_e_sequencial_e_unico(): void
    {
        $admin = $this->user('admin');

        $first = $this->order($admin);
        $second = $this->order($admin);

        $this->assertNotSame($first->code, $second->code);
        $this->assertMatchesRegularExpression('/^LOC-\d{4}$/', $first->code);
        $this->assertMatchesRegularExpression('/^LOC-\d{4}$/', $second->code);
    }
}
