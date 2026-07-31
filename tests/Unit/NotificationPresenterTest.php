<?php

namespace Tests\Unit;

use App\Domain\Enums\NotificationType;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shape do item do painel do sino (specs/22 §4.4). */
class NotificationPresenterTest extends TestCase
{
    use RefreshDatabase;

    private function card(): Card
    {
        $board = Board::create(['name' => 'Comercial']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1]);

        return Card::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Locação de som',
        ]);
    }

    private function notification(Card $card, ?User $actor, array $data): UserNotification
    {
        return UserNotification::create([
            'user_id' => User::factory()->create()->id,
            'actor_id' => $actor?->id,
            'card_id' => $card->id,
            'board_id' => $card->board_id,
            'type' => NotificationType::CardAssigned,
            'data' => $data,
        ]);
    }

    public function test_monta_o_rotulo_do_card_com_id_e_titulo(): void
    {
        $card = $this->card();
        $actor = User::factory()->create(['name' => 'Kaio Gomes'])->fresh();

        $item = NotificationPresenter::item($this->notification($card, $actor, [
            'card_title' => 'Locação de som',
            'actor_name' => 'Kaio Gomes',
        ]));

        $this->assertSame("#{$card->id} - Locação de som", $item['card_label']);
        $this->assertSame('Kaio Gomes', $item['actor_name']);
        $this->assertSame('KG', $item['actor_initials']);
        $this->assertSame('te colocou como responsável do card', $item['action_text']);
        $this->assertFalse($item['is_read']);
    }

    public function test_sem_autor_exibe_sistema(): void
    {
        $item = NotificationPresenter::item($this->notification($this->card(), null, [
            'card_title' => 'Locação de som',
            'actor_name' => null,
        ]));

        $this->assertSame('Sistema', $item['actor_name']);
        $this->assertNull($item['actor_initials']);
        $this->assertNull($item['actor_avatar_url']);
    }

    public function test_usa_o_titulo_do_snapshot_mesmo_apos_o_card_ser_renomeado(): void
    {
        $card = $this->card();
        $notification = $this->notification($card, null, ['card_title' => 'Locação de som']);

        $card->update(['title' => 'Outro título']);

        $this->assertSame("#{$card->id} - Locação de som", NotificationPresenter::item($notification)['card_label']);
    }
}
