<?php

namespace Tests\Feature\Fornecedores;

use App\Domain\Enums\PessoaTipo;
use App\Domain\Enums\UserRole;
use App\Models\Event;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submenu "Categorias" dentro de Fornecedores (gestão de categorias: criar/editar/excluir +
 * contador de fornecedores por categoria). O CRUD em si já existia; o que faltava era o link na
 * sidebar, visível só para quem pode de fato acessar `fornecedor-categorias.*` (admin/coordenador
 * não restrito por evento — specs/20), espelhando o mesmo critério usado para Setores/Empresas.
 */
class FornecedorCategoriaMenuTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'active' => true,
        ])->fresh();
    }

    public function test_admin_ve_o_submenu_de_categorias(): void
    {
        $admin = $this->user(UserRole::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('fornecedor-categorias.index'), false)
            ->assertSee('Categorias');
    }

    public function test_coordenador_sem_restricao_de_evento_ve_o_submenu(): void
    {
        $coordenador = $this->user(UserRole::Coordenador);

        $this->actingAs($coordenador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('fornecedor-categorias.index'), false);
    }

    public function test_coordenador_restrito_por_evento_nao_ve_o_submenu(): void
    {
        $coordenador = $this->user(UserRole::Coordenador);
        $event = Event::create([
            'name' => 'Evento X',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $coordenador->events()->attach($event);

        $this->assertTrue($coordenador->isEventScoped());

        $this->actingAs($coordenador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('fornecedor-categorias.index'), false);
    }

    public function test_usuario_comum_nao_ve_o_submenu_e_nao_acessa_a_rota(): void
    {
        $usuario = $this->user(UserRole::Usuario);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('fornecedor-categorias.index'), false);

        $this->actingAs($usuario)
            ->get(route('fornecedor-categorias.index'))
            ->assertForbidden();
    }

    public function test_listagem_mostra_o_contador_de_fornecedores_por_categoria(): void
    {
        $admin = $this->user(UserRole::Admin);

        $categoria = FornecedorCategoria::create(['nome' => 'Som', 'active' => true]);
        $outra = FornecedorCategoria::create(['nome' => 'Cenografia', 'active' => true]);

        Fornecedor::create([
            'type' => PessoaTipo::PJ->value,
            'name' => 'Fornecedor A',
            'document' => '11222333000181',
            'fornecedor_categoria_id' => $categoria->id,
            'active' => true,
        ]);
        Fornecedor::create([
            'type' => PessoaTipo::PJ->value,
            'name' => 'Fornecedor B',
            'document' => '11444777000161',
            'fornecedor_categoria_id' => $categoria->id,
            'active' => true,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('fornecedor-categorias.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Som', $html);
        $this->assertStringContainsString('Cenografia', $html);

        $categorias = $this->actingAs($admin)
            ->get(route('fornecedor-categorias.index'))
            ->viewData('categorias')
            ->getCollection();

        $this->assertSame(2, $categorias->firstWhere('id', $categoria->id)->fornecedores_count);
        $this->assertSame(0, $categorias->firstWhere('id', $outra->id)->fornecedores_count);
    }
}
