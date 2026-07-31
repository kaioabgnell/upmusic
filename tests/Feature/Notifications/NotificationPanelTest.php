<?php

namespace Tests\Feature\Notifications;

use App\Domain\Enums\NotificationType;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Event;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoints do painel do sino (specs/22 §4.3): paginação por cursor, filtro, contador,
 * marcar como lida e escopo de visibilidade.
 */
class NotificationPanelTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, UserRole $role = UserRole::Admin): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role->value,
            'active' => true,
        ])->fresh();
    }

    private function board(string $name = 'Comercial'): Board
    {
        $board = Board::create(['name' => $name]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        return $board;
    }

    private function card(Board $board, array $attributes = []): Card
    {
        return Card::create(array_merge([
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->value('id'),
            'title' => 'Locação de som',
        ], $attributes));
    }

    private function event(string $name): Event
    {
        return Event::create([
            'name' => $name,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'active' => true,
        ]);
    }

    /** Cria notificações diretamente: aqui o alvo é a leitura, não o gatilho. */
    private function notify(User $to, Card $card, ?User $actor = null, ?string $readAt = null): UserNotification
    {
        return UserNotification::create([
            'user_id' => $to->id,
            'actor_id' => $actor?->id,
            'card_id' => $card->id,
            'board_id' => $card->board_id,
            'type' => NotificationType::CardAssigned,
            'data' => ['card_title' => $card->title, 'actor_name' => $actor?->name],
            'read_at' => $readAt,
        ]);
    }

    // Listagem e paginação ---------------------------------------------------

    public function test_lista_devolve_dez_itens_e_cursor_e_a_segunda_pagina_continua_sem_repetir(): void
    {
        $user = $this->user('Maria Souza');
        $actor = $this->user('Kaio Gomes');
        $card = $this->card($this->board());

        $criadas = collect(range(1, 25))->map(fn () => $this->notify($user, $card, $actor)->id)->reverse()->values();

        $primeira = $this->actingAs($user)->getJson(route('notifications.index'))->assertOk();
        $primeira->assertJsonCount(10, 'items');

        $idsPrimeira = collect($primeira->json('items'))->pluck('id');
        $this->assertSame($criadas->take(10)->all(), $idsPrimeira->all(), 'A primeira página deve vir da mais recente para a mais antiga.');
        $this->assertSame($idsPrimeira->last(), $primeira->json('next_cursor'));

        $segunda = $this->actingAs($user)
            ->getJson(route('notifications.index', ['antes' => $primeira->json('next_cursor')]))
            ->assertOk();

        $idsSegunda = collect($segunda->json('items'))->pluck('id');
        $this->assertSame($criadas->slice(10, 10)->values()->all(), $idsSegunda->all());
        $this->assertEmpty($idsPrimeira->intersect($idsSegunda), 'As páginas não podem repetir itens.');
    }

    public function test_ultima_pagina_devolve_cursor_nulo(): void
    {
        $user = $this->user('Maria Souza');
        $card = $this->card($this->board());
        collect(range(1, 3))->each(fn () => $this->notify($user, $card));

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('next_cursor', null);
    }

    public function test_paginacao_por_cursor_nao_pula_item_quando_chega_notificacao_nova(): void
    {
        $user = $this->user('Maria Souza');
        $card = $this->card($this->board());
        $antigas = collect(range(1, 15))->map(fn () => $this->notify($user, $card)->id)->reverse()->values();

        $primeira = $this->actingAs($user)->getJson(route('notifications.index'))->assertOk();

        // Chega uma notificação nova entre as duas páginas (o caso que quebra offset).
        $this->notify($user, $card);

        $segunda = $this->actingAs($user)
            ->getJson(route('notifications.index', ['antes' => $primeira->json('next_cursor')]))
            ->assertOk();

        $this->assertSame($antigas->slice(10)->values()->all(), collect($segunda->json('items'))->pluck('id')->all());
    }

    public function test_filtro_nao_lidas_devolve_apenas_as_nao_lidas(): void
    {
        $user = $this->user('Maria Souza');
        $card = $this->card($this->board());
        $lida = $this->notify($user, $card, readAt: now()->toDateTimeString());
        $naoLida = $this->notify($user, $card);

        $response = $this->actingAs($user)
            ->getJson(route('notifications.index', ['filtro' => 'nao_lidas']))
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->assertSame($naoLida->id, $response->json('items.0.id'));
        $this->assertNotSame($lida->id, $response->json('items.0.id'));
        $this->assertSame(1, $response->json('unread_count'));
    }

    public function test_item_traz_texto_autor_e_link_do_card(): void
    {
        $user = $this->user('Maria Souza');
        $actor = $this->user('Kaio Gomes');
        $card = $this->card($this->board());
        $this->notify($user, $card, $actor);

        $item = $this->actingAs($user)->getJson(route('notifications.index'))->assertOk()->json('items.0');

        $this->assertSame('Kaio Gomes', $item['actor_name']);
        $this->assertSame('KG', $item['actor_initials']);
        $this->assertSame('te colocou como responsável do card', $item['action_text']);
        $this->assertSame("#{$card->id} - Locação de som", $item['card_label']);
        $this->assertSame(route('boards.show.card', ['board' => $card->board_id, 'card' => $card->id]), $item['url']);
        $this->assertFalse($item['is_read']);
    }

    // Contador ---------------------------------------------------------------

    public function test_contador_bate_com_as_nao_lidas_visiveis(): void
    {
        $user = $this->user('Maria Souza');
        $card = $this->card($this->board());
        $this->notify($user, $card);
        $this->notify($user, $card);
        $this->notify($user, $card, readAt: now()->toDateTimeString());

        $this->actingAs($user)->getJson(route('notifications.count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    public function test_contador_ignora_notificacoes_de_outros_usuarios(): void
    {
        $user = $this->user('Maria Souza');
        $outro = $this->user('João Lima');
        $card = $this->card($this->board());
        $this->notify($outro, $card);

        $this->actingAs($user)->getJson(route('notifications.count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    // Visibilidade -----------------------------------------------------------

    public function test_card_excluido_some_da_lista_e_do_contador(): void
    {
        $user = $this->user('Maria Souza');
        $card = $this->card($this->board());
        $this->notify($user, $card);
        $card->delete();

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_usuario_comum_sem_acesso_ao_quadro_nao_ve_a_notificacao(): void
    {
        $semAcesso = $this->user('Maria Souza', UserRole::Usuario);
        $comAcesso = $this->user('João Lima', UserRole::Usuario);
        $board = $this->board();
        $comAcesso->boards()->attach($board->id);
        $card = $this->card($board);

        $this->notify($semAcesso, $card);
        $this->notify($comAcesso, $card);

        $this->actingAs($semAcesso)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('unread_count', 0);

        $this->actingAs($comAcesso)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('unread_count', 1);
    }

    /**
     * Regressão do bug reportado: responsável de um card em quadro sem vínculo não recebia nada.
     * Ser o responsável atual basta para ver a notificação e abrir o card (specs/22 §6).
     */
    public function test_responsavel_recebe_notificacao_de_card_em_quadro_sem_acesso(): void
    {
        $responsavel = $this->user('Alice', UserRole::Usuario);
        $outroQuadro = $this->board('Conclusão');
        $responsavel->boards()->attach($outroQuadro->id);   // só tem acesso a este

        // Sem notify() manual: quem cria a notificação aqui é o próprio observer, como em produção.
        $card = $this->card($this->board('Orçamentos'), ['assignee_id' => $responsavel->id]);

        $this->actingAs($responsavel)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('unread_count', 1);

        // E o link da notificação realmente abre o card, em vez de dar 403.
        $this->actingAs($responsavel)
            ->get(route('boards.show.card', ['board' => $card->board_id, 'card' => $card->id]))
            ->assertOk();
    }

    public function test_deixar_de_ser_responsavel_tira_a_notificacao_do_quadro_sem_acesso(): void
    {
        $antigo = $this->user('Alice', UserRole::Usuario);
        $novo = $this->user('Bruno', UserRole::Usuario);
        $card = $this->card($this->board('Orçamentos'), ['assignee_id' => $antigo->id]);

        $card->update(['assignee_id' => $novo->id]);

        $this->actingAs($antigo)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($antigo)
            ->get(route('boards.show.card', ['board' => $card->board_id, 'card' => $card->id]))
            ->assertForbidden();
    }

    public function test_ser_responsavel_nao_libera_escrita_no_card(): void
    {
        $responsavel = $this->user('Alice', UserRole::Usuario);
        $card = $this->card($this->board('Orçamentos'), ['assignee_id' => $responsavel->id]);

        // Leitura liberada, escrita continua presa ao acesso ao quadro.
        $this->actingAs($responsavel)->getJson(route('cards.show', $card))->assertOk();
        $this->actingAs($responsavel)
            ->putJson(route('cards.update', $card), ['title' => 'Alterado'])
            ->assertForbidden();
        $this->actingAs($responsavel)->deleteJson(route('cards.destroy', $card))->assertForbidden();
    }

    public function test_coordenador_restrito_nao_ve_card_fora_do_escopo_nem_sendo_responsavel(): void
    {
        $coordenador = $this->user('Ana Dias', UserRole::Coordenador);
        $coordenador->events()->attach($this->event('Festa Junina')->id);

        $this->card($this->board(), [
            'event_id' => $this->event('Réveillon')->id,
            'assignee_id' => $coordenador->id,
        ]);

        $this->actingAs($coordenador)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_admin_e_coordenador_veem_notificacao_de_qualquer_quadro(): void
    {
        $admin = $this->user('Kaio Gomes', UserRole::Admin);
        $coordenador = $this->user('Ana Dias', UserRole::Coordenador);
        $card = $this->card($this->board());

        $this->notify($admin, $card);
        $this->notify($coordenador, $card);

        $this->actingAs($admin)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'items');
        $this->actingAs($coordenador)->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_coordenador_restrito_por_evento_nao_ve_card_fora_do_escopo(): void
    {
        $coordenador = $this->user('Ana Dias', UserRole::Coordenador);
        $board = $this->board();

        $permitido = $this->event('Festa Junina');
        $fora = $this->event('Réveillon');
        $coordenador->events()->attach($permitido->id);

        $this->notify($coordenador, $this->card($board, ['event_id' => $permitido->id, 'title' => 'Som']));
        $this->notify($coordenador, $this->card($board, ['event_id' => $fora->id, 'title' => 'Buffet']));

        $response = $this->actingAs($coordenador)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('unread_count', 1);

        $this->assertStringContainsString('Som', $response->json('items.0.card_label'));
    }

    // Marcar como lida -------------------------------------------------------

    public function test_marcar_como_lida_atualiza_contador_e_e_idempotente(): void
    {
        $user = $this->user('Maria Souza');
        $card = $this->card($this->board());
        $notification = $this->notify($user, $card);
        $this->notify($user, $card);

        $this->actingAs($user)->postJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $lidaEm = $notification->fresh()->read_at;
        $this->assertNotNull($lidaEm);

        // Segunda chamada não reescreve o read_at.
        $this->travel(1)->minutes();
        $this->actingAs($user)->postJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertEquals($lidaEm, $notification->fresh()->read_at);
    }

    public function test_marcar_como_lida_notificacao_de_outro_usuario_retorna_403(): void
    {
        $dono = $this->user('Maria Souza');
        $intruso = $this->user('Kaio Gomes', UserRole::Admin);
        $notification = $this->notify($dono, $this->card($this->board()));

        $this->actingAs($intruso)->postJson(route('notifications.read', $notification))->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marcar_todas_como_lidas_zera_o_contador_sem_tocar_em_outros_usuarios(): void
    {
        $user = $this->user('Maria Souza');
        $outro = $this->user('João Lima');
        $card = $this->card($this->board());
        collect(range(1, 3))->each(fn () => $this->notify($user, $card));
        $doOutro = $this->notify($outro, $card);

        $this->actingAs($user)->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, UserNotification::where('user_id', $user->id)->whereNull('read_at')->count());
        $this->assertNull($doOutro->fresh()->read_at);
    }

    // Autenticação -----------------------------------------------------------

    public function test_rotas_exigem_autenticacao(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->get(route('notifications.count'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }
}
