<?php

namespace Tests\Feature\Notifications;

use App\Actions\Cards\CreateCard;
use App\Actions\Cards\UpdateCard;
use App\Domain\Enums\NotificationType;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gatilho das notificações de responsável (specs/22 §4.1). O observer é o único ponto de entrada,
 * por isso os testes exercitam as Actions reais (CreateCard/UpdateCard) e não o observer direto.
 */
class CardAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, UserRole $role = UserRole::Usuario, bool $active = true): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role->value,
            'active' => $active,
        ])->fresh();
    }

    private function board(): Board
    {
        $board = Board::create(['name' => 'Comercial']);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        return $board;
    }

    private function card(Board $board, array $attributes = []): Card
    {
        return app(CreateCard::class)->execute($board, array_merge([
            'title' => 'Locação de som',
        ], $attributes));
    }

    public function test_atribuir_responsavel_cria_notificacao_com_snapshot_do_card(): void
    {
        $actor = $this->user('Kaio Gomes', UserRole::Admin);
        $assignee = $this->user('Maria Souza');
        $board = $this->board();
        $card = $this->card($board);

        $this->actingAs($actor);
        app(UpdateCard::class)->execute($card, ['title' => $card->title, 'assignee_id' => $assignee->id], $actor);

        $notification = UserNotification::sole();

        $this->assertSame($assignee->id, $notification->user_id);
        $this->assertSame($actor->id, $notification->actor_id);
        $this->assertSame($card->id, $notification->card_id);
        $this->assertSame($board->id, $notification->board_id);
        $this->assertSame(NotificationType::CardAssigned, $notification->type);
        $this->assertSame('Locação de som', $notification->data['card_title']);
        $this->assertNull($notification->read_at);
    }

    public function test_auto_atribuicao_nao_gera_notificacao(): void
    {
        $actor = $this->user('Kaio Gomes', UserRole::Admin);
        $card = $this->card($this->board());

        $this->actingAs($actor);
        app(UpdateCard::class)->execute($card, ['title' => $card->title, 'assignee_id' => $actor->id], $actor);

        $this->assertSame(0, UserNotification::count());
    }

    public function test_trocar_responsavel_notifica_apenas_o_novo(): void
    {
        $actor = $this->user('Kaio Gomes', UserRole::Admin);
        $primeiro = $this->user('Maria Souza');
        $segundo = $this->user('João Lima');
        $card = $this->card($this->board());

        $this->actingAs($actor);
        app(UpdateCard::class)->execute($card, ['title' => $card->title, 'assignee_id' => $primeiro->id], $actor);
        app(UpdateCard::class)->execute($card->fresh(), ['title' => $card->title, 'assignee_id' => $segundo->id], $actor);

        $this->assertSame(1, UserNotification::where('user_id', $primeiro->id)->count());
        $this->assertSame(1, UserNotification::where('user_id', $segundo->id)->count());
    }

    public function test_remover_responsavel_nao_gera_notificacao(): void
    {
        $actor = $this->user('Kaio Gomes', UserRole::Admin);
        $assignee = $this->user('Maria Souza');
        $card = $this->card($this->board());

        $this->actingAs($actor);
        app(UpdateCard::class)->execute($card, ['title' => $card->title, 'assignee_id' => $assignee->id], $actor);
        app(UpdateCard::class)->execute($card->fresh(), ['title' => $card->title, 'assignee_id' => null], $actor);

        $this->assertSame(1, UserNotification::count());
    }

    public function test_card_criado_ja_com_responsavel_notifica(): void
    {
        $actor = $this->user('Kaio Gomes', UserRole::Admin);
        $assignee = $this->user('Maria Souza');

        $this->actingAs($actor);
        $card = $this->card($this->board(), ['assignee_id' => $assignee->id]);

        $notification = UserNotification::sole();
        $this->assertSame($assignee->id, $notification->user_id);
        $this->assertSame($card->id, $notification->card_id);
    }

    public function test_responsavel_inativo_nao_recebe_notificacao(): void
    {
        $actor = $this->user('Kaio Gomes', UserRole::Admin);
        $inativo = $this->user('Maria Souza', UserRole::Usuario, active: false);
        $card = $this->card($this->board());

        $this->actingAs($actor);
        app(UpdateCard::class)->execute($card, ['title' => $card->title, 'assignee_id' => $inativo->id], $actor);

        $this->assertSame(0, UserNotification::count());
    }

    public function test_card_criado_sem_sessao_registra_autor_nulo(): void
    {
        $assignee = $this->user('Maria Souza');

        // Sem actingAs: origem sem sessão (formulário externo, captura) → "Sistema" no painel.
        $this->card($this->board(), ['assignee_id' => $assignee->id]);

        $notification = UserNotification::sole();
        $this->assertNull($notification->actor_id);
        $this->assertNull($notification->data['actor_name']);
    }
}
