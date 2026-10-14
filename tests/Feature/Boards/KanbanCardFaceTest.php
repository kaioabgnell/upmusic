<?php

namespace Tests\Feature\Boards;

use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\User;
use App\Support\CardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Valor mostrado na face do card no Kanban.
 *
 * Era o "Banco de Preços" (`estimated_value`), que é preço de REFERÊNCIA — a média do histórico
 * daquele fornecedor, sugerida na hora de orçar. No quadro, quem passa o olho quer saber quanto a
 * coisa custou, e isso é o valor realizado.
 */
class KanbanCardFaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_expoe_o_valor_realizado_do_card(): void
    {
        $board = Board::create(['name' => 'Orçamentos']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Locação de som',
            'estimated_value' => 235,   // referência do Banco de Preços
            'unit_value' => 210,
            'quantity' => 2,
            'actual_value' => 420,      // 210 × 2
        ]);

        $payload = CardPresenter::compact($card->fresh());

        $this->assertEquals(420, (float) $payload['actual_value']);
        // O previsto continua no payload: a visão de LISTA do quadro tem coluna própria para ele.
        $this->assertEquals(235, (float) $payload['estimated_value']);
    }

    public function test_face_do_card_mostra_o_realizado_e_nao_o_banco_de_precos(): void
    {
        $blade = file_get_contents(resource_path('views/boards/show.blade.php'));

        $this->assertStringContainsString('x-show="card.actual_value !== null"', $blade);
        $this->assertStringNotContainsString('x-show="card.estimated_value !== null"', $blade);
    }

    public function test_card_sem_valor_realizado_nao_mostra_linha_de_valor(): void
    {
        $board = Board::create(['name' => 'Orçamentos']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Ainda sem custo',
            'estimated_value' => 500,
        ]);

        // null (não 0): é o que faz o `x-show` esconder a linha em vez de exibir "R$ 0,00".
        $this->assertNull(CardPresenter::compact($card->fresh())['actual_value']);
    }

    public function test_quadro_renderiza_com_o_valor_realizado_na_face(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();
        $board = Board::create(['name' => 'Orçamentos']);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        $this->actingAs($admin)
            ->get(route('boards.show', $board))
            ->assertOk()
            ->assertSee('card.actual_value', false);
    }
}
