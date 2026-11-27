<?php

namespace Tests\Feature\AccessProfiles;

use App\Domain\Enums\AccessModule;
use App\Models\Event;

/** "Eventos visíveis: somente os selecionados" para qualquer não-admin (specs/28 §6). */
class EventScopeTest extends AccessProfileTestCase
{
    public function test_kanban_mostra_so_os_cards_dos_eventos_do_usuario(): void
    {
        $board = $this->board();
        $meu = $this->event('Copa 2026');
        $outro = $this->event('Festa Junina');
        $doEvento = $this->card($board, ['title' => 'Do meu evento', 'event_id' => $meu->id]);
        $alheio = $this->card($board, ['title' => 'De outro evento', 'event_id' => $outro->id]);
        $semEvento = $this->card($board, ['title' => 'Sem evento']);

        $user = $this->giveBoards($this->userAs('sem-acesso'), $board);
        $user->events()->attach($meu->id);

        $this->actingAs($user)->getJson(route('boards.kanban.data', $board))->assertOk()
            ->assertSee('Do meu evento')
            ->assertDontSee('De outro evento')
            ->assertDontSee('Sem evento');

        $this->actingAs($user)->getJson(route('cards.show', $doEvento))->assertOk();
        $this->actingAs($user)->getJson(route('cards.show', $alheio))->assertForbidden();
        $this->actingAs($user)->getJson(route('cards.show', $semEvento))->assertForbidden();
    }

    public function test_restricao_nao_esconde_cadastros_que_nao_sao_de_evento(): void
    {
        $user = $this->userAs('sem-acesso', modules: [AccessModule::Setores, AccessModule::Empresas]);
        $user->events()->attach($this->event()->id);

        $this->actingAs($user)->get(route('setores.index'))->assertOk();
        $this->actingAs($user)->get(route('empresas.index'))->assertOk();
    }

    public function test_restrito_cria_evento_e_o_evento_entra_nos_visiveis_dele(): void
    {
        $user = $this->userAs('sem-acesso', modules: [AccessModule::Events]);
        $meu = $this->event('Copa 2026');
        $alheio = $this->event('Festa Junina');
        $user->events()->attach($meu->id);

        $this->actingAs($user)->post(route('eventos.store'), [
            'name' => 'Réveillon', 'tipo' => \App\Domain\Enums\EventTipo::cases()[0]->value,
            'start_date' => '2026-12-31', 'end_date' => '2027-01-01',
        ])->assertRedirect(route('eventos.index'));

        $novo = Event::where('name', 'Réveillon')->firstOrFail();
        $this->assertEqualsCanonicalizing([$meu->id, $novo->id], $user->events()->pluck('events.id')->all());

        $this->actingAs($user)->get(route('eventos.index'))->assertOk()
            ->assertSee('Réveillon')->assertSee('Copa 2026')->assertDontSee('Festa Junina');
        $this->actingAs($user)->get(route('eventos.edit', $alheio))->assertForbidden();
    }

    public function test_quem_ve_todos_os_eventos_cria_sem_vinculo(): void
    {
        $user = $this->userAs('sem-acesso', modules: [AccessModule::Events]);

        $this->actingAs($user)->post(route('eventos.store'), [
            'name' => 'Réveillon', 'tipo' => \App\Domain\Enums\EventTipo::cases()[0]->value,
            'start_date' => '2026-12-31', 'end_date' => '2027-01-01',
        ])->assertRedirect(route('eventos.index'));

        $this->assertSame(0, $user->events()->count());
    }

    public function test_financeiro_dos_eventos_mostra_so_os_eventos_do_usuario(): void
    {
        $meu = $this->event('Copa 2026');
        $alheio = $this->event('Festa Junina');
        $user = $this->userAs('sem-acesso', modules: [AccessModule::Finance]);
        $user->events()->attach($meu->id);

        $this->actingAs($user)->get(route('finance.show', $meu))->assertOk();
        $this->actingAs($user)->get(route('finance.show', $alheio))->assertForbidden();
    }
}
