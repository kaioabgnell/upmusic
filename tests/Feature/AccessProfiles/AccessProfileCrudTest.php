<?php

namespace Tests\Feature\AccessProfiles;

use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;

/** Cadastro de Perfis de acesso (specs/28 §7.1). */
class AccessProfileCrudTest extends AccessProfileTestCase
{
    public function test_so_o_admin_acessa_perfis_de_acesso(): void
    {
        $this->get(route('access-profiles.index'))->assertRedirect(route('login'));

        // Nem com todos os módulos ligados: Perfis de acesso nunca entra em perfil.
        $todos = $this->userAs('sem-acesso', modules: AccessModule::cases());
        $this->actingAs($todos)->get(route('access-profiles.index'))->assertForbidden();
        $this->actingAs($todos)->post(route('access-profiles.store'), ['name' => 'X'])->assertForbidden();

        $admin = $this->userAs('admin');
        $this->actingAs($admin)->get(route('access-profiles.index'))->assertOk()->assertSee('Perfis de acesso');
        $this->actingAs($admin)->get(route('access-profiles.create'))->assertOk()
            ->assertSee('Configurar quadros')->assertSee('Banco de Preços')->assertSee('Licitações');
    }

    public function test_admin_cria_perfil_com_modulos_e_quadros(): void
    {
        $orcamentos = $this->board('Orçamentos');
        $this->board('Jurídico');

        $this->actingAs($this->userAs('admin'))->post(route('access-profiles.store'), [
            'name' => '  Comercial ',
            'modules' => ['empresas', 'prices'],
            'boards' => [$orcamentos->id],
        ])->assertRedirect(route('access-profiles.index'))->assertSessionHas('success');

        $profile = AccessProfile::where('name', 'Comercial')->firstOrFail();
        $this->assertEqualsCanonicalizing(['empresas', 'prices'], $profile->modules()->pluck('module')->all());
        $this->assertSame([$orcamentos->id], $profile->boards()->pluck('boards.id')->all());
    }

    public function test_nome_repetido_modulo_invalido_e_quadro_de_custos_fixos_sao_recusados(): void
    {
        AccessProfile::create(['name' => 'Comercial']);
        $fixed = $this->board('Custos Fixos', fixedCosts: true);

        $this->actingAs($this->userAs('admin'))->post(route('access-profiles.store'), [
            'name' => 'Comercial',
            'modules' => ['xpto'],
            'boards' => [$fixed->id],
        ])->assertSessionHasErrors(['name', 'modules.0', 'boards.0']);

        $this->assertSame(1, AccessProfile::count());
    }

    public function test_editar_troca_modulos_e_quadros_e_mantem_vinculo_de_quadro_inativo(): void
    {
        $ativo = $this->board('Orçamentos');
        $inativo = $this->board('Antigo', active: false);
        $novo = $this->board('Produção');
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $profile->modules()->create(['module' => 'empresas']);
        $profile->boards()->attach([$ativo->id, $inativo->id]);

        $this->actingAs($this->userAs('admin'))->put(route('access-profiles.update', $profile), [
            'name' => 'Comercial',
            'modules' => ['setores'],
            'boards' => [$novo->id],
        ])->assertRedirect(route('access-profiles.index'));

        $this->assertSame(['setores'], $profile->modules()->pluck('module')->all());
        $this->assertEqualsCanonicalizing([$inativo->id, $novo->id], $profile->boards()->pluck('boards.id')->all());
    }

    public function test_desligar_tudo_salva_perfil_sem_modulos(): void
    {
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $profile->modules()->create(['module' => 'empresas']);

        $this->actingAs($this->userAs('admin'))->put(route('access-profiles.update', $profile), ['name' => 'Comercial'])
            ->assertRedirect(route('access-profiles.index'));

        $this->assertSame(0, $profile->modules()->count());
    }

    public function test_duplicar_copia_modulos_e_quadros_com_nome_livre(): void
    {
        $board = $this->board();
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $profile->modules()->create(['module' => 'empresas']);
        $profile->boards()->attach($board->id);
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('access-profiles.duplicate', $profile))->assertRedirect();
        $this->actingAs($admin)->post(route('access-profiles.duplicate', $profile))->assertRedirect();

        $copy = AccessProfile::where('name', 'Cópia de Comercial')->firstOrFail();
        $this->assertSame(['empresas'], $copy->modules()->pluck('module')->all());
        $this->assertSame([$board->id], $copy->boards()->pluck('boards.id')->all());
        $this->assertTrue(AccessProfile::where('name', 'Cópia de Comercial (2)')->exists());
    }

    public function test_excluir_e_bloqueado_com_usuarios_vinculados(): void
    {
        $user = $this->userAs('usuario');
        $profile = $user->accessProfile;
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->delete(route('access-profiles.destroy', $profile))
            ->assertSessionHas('error', 'Este perfil está vinculado a 1 usuário(s). Troque o perfil deles antes de excluir.');
        $this->assertModelExists($profile);

        // Usuário na lixeira não bloqueia: fica sem perfil.
        $user->delete();
        $this->actingAs($admin)->delete(route('access-profiles.destroy', $profile))->assertSessionHas('success');
        $this->assertModelMissing($profile);
        $this->assertNull($user->fresh()->access_profile_id);
    }

    public function test_lista_mostra_contagens_e_todos_para_configurar_quadros(): void
    {
        $this->userAs('coordenador', ['name' => 'Ana']);
        $this->userAs('usuario', ['name' => 'Bruno']);

        $this->actingAs($this->userAs('admin'))->get(route('access-profiles.index'))
            ->assertOk()
            ->assertSee('13 de '.count(AccessModule::cases()))
            ->assertSee('2 de '.count(AccessModule::cases()))
            ->assertSee('Todos')
            ->assertSee('1 usuário');
    }
}
