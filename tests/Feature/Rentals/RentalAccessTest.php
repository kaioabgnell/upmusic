<?php

namespace Tests\Feature\Rentals;

use App\Domain\Enums\UserRole;

/** Acesso ao módulo (specs/24 §11). */
class RentalAccessTest extends RentalTestCase
{
    public function test_admin_e_coordenador_sem_escopo_entram(): void
    {
        $this->actingAs($this->user(UserRole::Admin))->get(route('rentals.board'))->assertOk();
        $this->actingAs($this->user(UserRole::Coordenador))->get(route('rentals.board'))->assertOk();
    }

    public function test_coordenador_restrito_por_evento_nao_acessa(): void
    {
        $coordenador = $this->user(UserRole::Coordenador);
        $event = \App\Models\Event::create([
            'name' => 'Evento X', 'location' => 'Goiânia', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'active' => true,
        ]);
        $coordenador->events()->attach($event->id);

        $this->actingAs($coordenador->fresh())->get(route('rentals.board'))->assertForbidden();
    }

    public function test_usuario_sem_checkbox_nao_acessa_e_com_checkbox_acessa(): void
    {
        $this->actingAs($this->user(UserRole::Usuario))
            ->get(route('rentals.board'))
            ->assertForbidden();

        $this->actingAs($this->user(UserRole::Usuario, ['can_access_rentals' => true]))
            ->get(route('rentals.board'))
            ->assertOk();
    }

    public function test_lista_de_pedidos_renderiza(): void
    {
        $admin = $this->user(UserRole::Admin);
        $this->order($admin, ['client_name' => 'Cliente']);

        $this->actingAs($admin)->get(route('rentals.orders.index'))->assertOk()->assertSee('LOC-');
    }

    public function test_codigo_do_pedido_e_sequencial_e_unico(): void
    {
        $admin = $this->user(UserRole::Admin);

        $first = $this->order($admin);
        $second = $this->order($admin);

        $this->assertNotSame($first->code, $second->code);
        $this->assertMatchesRegularExpression('/^LOC-\d{4}$/', $first->code);
        $this->assertMatchesRegularExpression('/^LOC-\d{4}$/', $second->code);
    }
}
