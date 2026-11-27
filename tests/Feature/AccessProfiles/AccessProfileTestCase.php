<?php

namespace Tests\Feature\AccessProfiles;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base comum dos testes de Perfis de acesso (specs/28). */
abstract class AccessProfileTestCase extends TestCase
{
    use RefreshDatabase;

    protected function board(string $name = 'Orçamentos', bool $fixedCosts = false, bool $active = true): Board
    {
        $board = Board::create(['name' => $name, 'fixed_costs' => $fixedCosts, 'active' => $active]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Saída', 'position' => 2, 'is_final' => true]);

        return $board;
    }

    protected function card(Board $board, array $attributes = []): Card
    {
        return Card::create($attributes + [
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->orderBy('position')->value('id'),
            'title' => 'Locação de som',
        ]);
    }

    protected function event(string $name = 'Festa Junina'): Event
    {
        return Event::create([
            'name' => $name, 'location' => 'Goiânia', 'start_date' => '2026-06-20', 'end_date' => '2026-06-22', 'active' => true,
        ]);
    }
}
