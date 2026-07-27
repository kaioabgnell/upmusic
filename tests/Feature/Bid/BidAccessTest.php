<?php

namespace Tests\Feature\Bid;

use App\Domain\Enums\UserRole;
use App\Models\BidCompany;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\BidCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acesso exclusivo do Admin (specs/21 §7). O bloqueio é de servidor — esconder o menu é só a
 * camada visual.
 */
class BidAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        // `->fresh()` é obrigatório: o modelo recém-criado não traz `avatar_path` em memória e o
        // componente de avatar do layout quebra com `preventAccessingMissingAttributes` ligado.
        return User::factory()->create([
            'name' => 'Usuário '.$role->value,
            'email' => $role->value.'@teste.local',
            'role' => $role->value,
            'active' => true,
        ])->fresh();
    }

    private function urls(): array
    {
        $company = BidCompany::create([
            'corporate_name' => 'EMPRESA TESTE',
            'cnpj' => '19131243000197',
            'size' => 'me',
        ]);

        return [
            route('bid.dashboard'),
            route('bid.companies.index'),
            route('bid.companies.create'),
            route('bid.companies.show', $company),
            route('bid.companies.edit', $company),
            route('bid.notices.index'),
            route('bid.notices.create'),
            route('bid.reports.index'),
            route('bid.settings.index'),
        ];
    }

    public function test_admin_acessa_todas_as_telas_do_modulo(): void
    {
        $this->seed(BidCatalogSeeder::class);
        $admin = $this->user(UserRole::Admin);

        foreach ($this->urls() as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_coordenador_e_usuario_recebem_403_em_todas_as_rotas(): void
    {
        $this->seed(BidCatalogSeeder::class);
        $urls = $this->urls();

        foreach ([UserRole::Coordenador, UserRole::Usuario] as $role) {
            $user = $this->user($role);

            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_coordenador_restrito_por_evento_tambem_e_barrado(): void
    {
        $this->seed(BidCatalogSeeder::class);

        $coordenador = $this->user(UserRole::Coordenador);
        $event = Event::create([
            'name' => 'Evento X',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $coordenador->events()->attach($event);

        $this->assertTrue($coordenador->isEventScoped());
        $this->actingAs($coordenador)->get(route('bid.dashboard'))->assertForbidden();
    }

    public function test_escrita_tambem_e_bloqueada_para_nao_admin(): void
    {
        $coordenador = $this->user(UserRole::Coordenador);

        $this->actingAs($coordenador)
            ->post(route('bid.companies.store'), [
                'corporate_name' => 'INVASORA LTDA',
                'cnpj' => '19131243000197',
                'size' => 'me',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('bid_companies', 0);
    }

    public function test_menu_de_licitacoes_aparece_so_para_admin(): void
    {
        $this->seed(BidCatalogSeeder::class);

        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Painel de Licitações')
            ->assertSee('Análise de Editais');

        $this->actingAs($this->user(UserRole::Coordenador))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Painel de Licitações')
            ->assertDontSee('Análise de Editais');
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get(route('bid.dashboard'))->assertRedirect(route('login'));
    }
}
