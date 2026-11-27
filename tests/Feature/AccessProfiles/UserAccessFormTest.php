<?php

namespace Tests\Feature\AccessProfiles;

use App\Models\AccessProfile;
use App\Models\User;

/** Cadastro e lista de usuários com Administrador, Perfil de acesso e Eventos visíveis (specs/28 §7.2). */
class UserAccessFormTest extends AccessProfileTestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Maria Souza',
            'email' => 'maria@teste.local',
            'password' => 'Senha@12345',
            'password_confirmation' => 'Senha@12345',
            'active' => '1',
        ];
    }

    public function test_telas_do_cadastro_renderizam(): void
    {
        $admin = $this->userAs('admin');
        $user = $this->userAs('usuario');

        $this->actingAs($admin)->get(route('users.create'))->assertOk()
            ->assertSee('Perfil de acesso')->assertSee('Eventos visíveis')->assertSee('Administrador');
        $this->actingAs($admin)->get(route('users.edit', $user))->assertOk();
    }

    public function test_quem_nao_e_admin_precisa_de_perfil(): void
    {
        $this->actingAs($this->userAs('admin'))->post(route('users.store'), $this->payload())
            ->assertSessionHasErrors(['access_profile_id' => 'Escolha um perfil de acesso ou marque Administrador.']);

        $profile = AccessProfile::create(['name' => 'Comercial']);
        $this->actingAs($this->userAs('admin'))
            ->post(route('users.store'), $this->payload(['access_profile_id' => $profile->id]))
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'maria@teste.local')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertSame($profile->id, $user->access_profile_id);
    }

    public function test_administrador_grava_sem_perfil_e_sem_eventos(): void
    {
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $event = $this->event();

        $this->actingAs($this->userAs('admin'))->post(route('users.store'), $this->payload([
            'is_admin' => '1', 'access_profile_id' => $profile->id, 'event_scope' => 'selected', 'events' => [$event->id],
        ]))->assertRedirect(route('users.index'));

        $user = User::where('email', 'maria@teste.local')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertNull($user->access_profile_id);
        $this->assertSame(0, $user->events()->count());
    }

    public function test_eventos_visiveis_somente_os_selecionados_exige_evento(): void
    {
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $event = $this->event();
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('users.store'), $this->payload([
            'access_profile_id' => $profile->id, 'event_scope' => 'selected',
        ]))->assertSessionHasErrors('events');

        $this->actingAs($admin)->post(route('users.store'), $this->payload([
            'access_profile_id' => $profile->id, 'event_scope' => 'selected', 'events' => [$event->id],
        ]))->assertRedirect(route('users.index'));

        $user = User::where('email', 'maria@teste.local')->firstOrFail();
        $this->assertSame([$event->id], $user->events()->pluck('events.id')->all());

        // "Todos os eventos" remove o vínculo.
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'active' => '1',
            'access_profile_id' => $profile->id, 'event_scope' => 'all', 'events' => [$event->id],
        ])->assertRedirect(route('users.index'));
        $this->assertSame(0, $user->events()->count());
    }

    public function test_marcar_administrador_limpa_perfil_e_eventos(): void
    {
        $user = $this->userAs('usuario');
        $user->events()->attach($this->event()->id);

        $this->actingAs($this->userAs('admin'))->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'active' => '1', 'is_admin' => '1',
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertNull($user->access_profile_id);
        $this->assertSame(0, $user->events()->count());
    }

    public function test_ninguem_tira_o_proprio_administrador_nem_o_do_ultimo_admin(): void
    {
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $unico = $this->userAs('admin');

        $this->actingAs($unico)->put(route('users.update', $unico), [
            'name' => $unico->name, 'email' => $unico->email, 'active' => '1', 'is_admin' => '0', 'access_profile_id' => $profile->id,
        ])->assertSessionHasErrors(['is_admin' => 'Você não pode tirar o seu próprio acesso de Administrador.']);

        // Outro admin, mas inativo: o alvo continua sendo o último admin ativo.
        $alvo = $this->userAs('admin');
        $inativo = $this->userAs('admin', ['active' => false]);
        $this->actingAs($inativo)->put(route('users.update', $unico), [
            'name' => $unico->name, 'email' => $unico->email, 'active' => '1', 'is_admin' => '0', 'access_profile_id' => $profile->id,
        ]);
        $this->assertTrue($unico->fresh()->is_admin);

        $this->actingAs($unico)->put(route('users.update', $alvo), [
            'name' => $alvo->name, 'email' => $alvo->email, 'active' => '1', 'is_admin' => '0', 'access_profile_id' => $profile->id,
        ])->assertRedirect(route('users.index'));
        $this->assertFalse($alvo->fresh()->is_admin);
        $this->assertSame($profile->id, $alvo->fresh()->access_profile_id);
    }

    public function test_lista_mostra_e_filtra_por_perfil_de_acesso(): void
    {
        $admin = $this->userAs('admin', ['name' => 'Kaio Admin']);
        $comercial = $this->userAs('usuario', ['name' => 'Bruno Comercial']);
        $comercial->accessProfile->update(['name' => 'Comercial']);
        $this->userAs('coordenador', ['name' => 'Ana Coordena']);
        // O admin logado aparece no topo da página; a lista é conferida com outro admin.
        $this->userAs('admin', ['name' => 'Zeca Admin']);

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('Administrador')->assertSee('Comercial');

        $this->actingAs($admin)->get(route('users.index', ['perfil' => $comercial->access_profile_id]))->assertOk()
            ->assertSee('Bruno Comercial')->assertDontSee('Ana Coordena')->assertDontSee('Zeca Admin');

        $this->actingAs($admin)->get(route('users.index', ['perfil' => 'admin']))->assertOk()
            ->assertSee('Zeca Admin')->assertDontSee('Bruno Comercial');
    }
}
