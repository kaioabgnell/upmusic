<?php

namespace Tests\Feature\AccessProfiles;

use App\Domain\Enums\AccessModule;
use App\Models\AccessProfileModule;
use App\Models\Fornecedor;

/** Middleware `module`, Policies e menu (specs/28 §3, §7.3 e §8.3). */
class ModuleAccessTest extends AccessProfileTestCase
{
    /** Uma tela representativa por módulo. */
    private const SCREENS = [
        'boards_configure' => 'boards.create',
        'setores' => 'setores.index',
        'empresas' => 'empresas.index',
        'cost_centers' => 'cost-centers.index',
        'bank_accounts' => 'bank-accounts.index',
        'fixed_costs' => 'fixed-costs.index',
        'fornecedores' => 'fornecedores.index',
        'fornecedor_categorias' => 'fornecedor-categorias.index',
        'events' => 'eventos.index',
        'templates' => 'templates.index',
        'finance' => 'finance.index',
        'prices' => 'prices.categorias.index',
        'finance_settings' => 'finance.settings.index',
        'captures' => 'captures.index',
        'rentals' => 'rentals.board',
        'bids' => 'bid.dashboard',
    ];

    public function test_todo_modulo_tem_uma_tela_no_teste(): void
    {
        $this->assertEqualsCanonicalizing(AccessModule::values(), array_keys(self::SCREENS));
    }

    public function test_cada_modulo_libera_so_a_sua_tela(): void
    {
        $admin = $this->userAs('admin');

        foreach (AccessModule::cases() as $module) {
            $route = route(self::SCREENS[$module->value]);
            $others = array_values(array_filter(AccessModule::cases(), fn ($m) => $m !== $module));

            $this->actingAs($this->userAs('sem-acesso', modules: $others))->get($route)
                ->assertForbidden();
            $this->actingAs($this->userAs('sem-acesso', modules: [$module]))->get($route)
                ->assertOk();
            $this->actingAs($admin)->get($route)->assertOk();
        }
    }

    public function test_usuarios_e_perfis_sao_so_do_admin(): void
    {
        $todos = $this->userAs('sem-acesso', modules: AccessModule::cases());

        $this->actingAs($todos)->get(route('users.index'))->assertForbidden();
        $this->actingAs($todos)->get(route('users.create'))->assertForbidden();
        $this->actingAs($todos)->get(route('access-profiles.index'))->assertForbidden();
    }

    public function test_menu_mostra_so_o_que_o_perfil_liga(): void
    {
        $orcamentos = $this->board('Orçamentos');
        $juridico = $this->board('Jurídico');
        $user = $this->giveBoards(
            $this->userAs('sem-acesso', modules: [AccessModule::Empresas, AccessModule::Prices]),
            $orcamentos,
        );

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(route('empresas.index'), false)
            ->assertSee(route('prices.categorias.index'), false)
            ->assertSee(route('boards.show', $orcamentos), false)
            ->assertDontSee(route('boards.show', $juridico), false)
            ->assertDontSee(route('setores.index'), false)
            ->assertDontSee(route('finance.index'), false)
            ->assertDontSee(route('rentals.board'), false)
            ->assertDontSee(route('bid.dashboard'), false)
            ->assertDontSee(route('captures.index'), false)
            ->assertDontSee(route('users.index'), false)
            ->assertDontSee(route('access-profiles.index'), false);
    }

    public function test_secao_some_quando_nenhum_item_dela_aparece(): void
    {
        $this->actingAs($this->userAs('sem-acesso'))->get(route('dashboard'))->assertOk()
            ->assertDontSee('>Cadastros<', false)
            ->assertDontSee('>Financeiro<', false)
            ->assertDontSee('>Administração<', false);

        $this->actingAs($this->userAs('admin'))->get(route('dashboard'))->assertOk()
            ->assertSee(route('access-profiles.index'), false);
    }

    public function test_desligar_o_modulo_vale_na_proxima_requisicao(): void
    {
        $user = $this->userAs('sem-acesso', modules: [AccessModule::Empresas]);

        $this->actingAs($user)->get(route('empresas.index'))->assertOk();

        AccessProfileModule::where('access_profile_id', $user->access_profile_id)->delete();

        $this->actingAs($user)->get(route('empresas.index'))->assertForbidden();
    }

    public function test_buscas_usadas_dentro_do_card_nao_dependem_de_modulo(): void
    {
        $user = $this->userAs('sem-acesso');
        $fornecedor = Fornecedor::create(['name' => 'Som Total', 'type' => 'PJ', 'document' => '11222333000181', 'active' => true]);

        $this->actingAs($user)->getJson(route('empresas.search', ['q' => 'up']))->assertOk();
        $this->actingAs($user)->getJson(route('finance.presets.index'))->assertOk();
        $this->actingAs($user)->getJson(route('fornecedores.price-history', $fornecedor))->assertOk();
    }

    public function test_categoria_rapida_libera_com_fornecedores(): void
    {
        $this->actingAs($this->userAs('sem-acesso'))
            ->postJson(route('fornecedor-categorias.quick'), ['nome' => 'Categoria de teste'])
            ->assertForbidden();

        $this->actingAs($this->userAs('sem-acesso', modules: [AccessModule::Fornecedores]))
            ->postJson(route('fornecedor-categorias.quick'), ['nome' => 'Categoria de teste'])
            ->assertSuccessful();
    }
}
