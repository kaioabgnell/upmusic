<?php

namespace Tests\Feature\Notifications;

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
 * Sino renderizado no layout autenticado (specs/22 §5.1). O badge precisa sair correto já no HTML,
 * via NotificationComposer — sem isso ele piscaria em 0 até o primeiro fetch do Alpine.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotifications(User $user, int $quantidade): void
    {
        $board = Board::create(['name' => 'Comercial']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1]);
        $card = Card::create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Locação de som']);

        foreach (range(1, $quantidade) as $ignored) {
            UserNotification::create([
                'user_id' => $user->id,
                'card_id' => $card->id,
                'board_id' => $board->id,
                'type' => NotificationType::CardAssigned,
                'data' => ['card_title' => $card->title],
            ]);
        }
    }

    public function test_sino_aparece_no_layout_com_o_total_de_nao_lidas(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();
        $this->seedNotifications($user, 3);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('fa-bell', false)
            ->assertSee('initialUnread: 3', false)
            ->assertSee(route('notifications.count'), false);
    }

    public function test_badge_comeca_em_zero_para_quem_nao_tem_notificacao(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('initialUnread: 0', false);
    }
}
