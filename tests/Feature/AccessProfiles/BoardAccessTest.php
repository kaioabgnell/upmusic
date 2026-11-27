<?php

namespace Tests\Feature\AccessProfiles;

use App\Domain\Enums\AccessModule;
use App\Models\AccessProfile;

/** Régua de acesso a quadro (specs/28 §4). */
class BoardAccessTest extends AccessProfileTestCase
{
    public function test_quadro_ligado_no_perfil_abre_e_os_outros_nao(): void
    {
        $meu = $this->board('Orçamentos');
        $alheio = $this->board('Jurídico');
        $user = $this->giveBoards($this->userAs('sem-acesso'), $meu);

        $this->actingAs($user)->get(route('boards.show', $meu))->assertOk();
        $this->actingAs($user)->get(route('boards.show', $alheio))->assertForbidden();
        $this->actingAs($user)->get(route('boards.index'))->assertOk()
            ->assertSee('Orçamentos')->assertDontSee('Jurídico');

        // Sem Configurar quadros não mexe na estrutura nem do próprio quadro.
        $this->actingAs($user)->get(route('boards.config', $meu))->assertForbidden();
    }

    public function test_configurar_quadros_abre_todos_inclusive_os_criados_depois_menos_custos_fixos(): void
    {
        $user = $this->userAs('sem-acesso', modules: [AccessModule::BoardsConfigure]);
        $fixed = $this->board('Custos Fixos', fixedCosts: true);
        $depois = $this->board('Criado depois');

        $this->actingAs($user)->get(route('boards.show', $depois))->assertOk();
        $this->actingAs($user)->get(route('boards.config', $depois))->assertOk();
        $this->actingAs($user)->get(route('boards.show', $fixed))->assertForbidden();
        $this->actingAs($user)->get(route('boards.config', $fixed))->assertForbidden();
    }

    public function test_quadro_novo_comeca_desligado_nos_perfis(): void
    {
        $user = $this->giveBoards($this->userAs('sem-acesso'), $this->board('Orçamentos'));

        $novo = $this->board('Produção');

        $this->actingAs($user)->get(route('boards.show', $novo))->assertForbidden();
        $this->assertSame(0, $novo->accessProfiles()->count());
    }

    public function test_responsavel_le_o_card_de_quadro_fora_do_perfil_mas_nao_edita(): void
    {
        $board = $this->board('Jurídico');
        $user = $this->userAs('sem-acesso');
        $card = $this->card($board, ['assignee_id' => $user->id]);

        $this->actingAs($user)->getJson(route('cards.show', $card))->assertOk();
        $this->actingAs($user)->putJson(route('cards.update', $card), ['title' => 'Outro'])->assertForbidden();
    }

    public function test_configuracao_do_quadro_mostra_os_perfis_que_o_acessam(): void
    {
        $board = $this->board('Orçamentos');
        $profile = AccessProfile::create(['name' => 'Comercial']);
        $profile->boards()->attach($board->id);
        AccessProfile::create(['name' => 'Jurídico']);

        $this->actingAs($this->userAs('admin'))->get(route('boards.config', $board))->assertOk()
            ->assertSee('Comercial')
            ->assertDontSee('>Jurídico<', false)
            ->assertSee(route('access-profiles.index'), false);
    }
}
