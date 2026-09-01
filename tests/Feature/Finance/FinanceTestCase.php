<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Event;
use App\Models\FinancePaymentSource;
use App\Models\FinanceSheet;
use App\Models\FornecedorCategoria;
use App\Models\User;
use App\Services\Finance\FinanceSheetProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base comum dos testes do Financeiro do Evento (specs/23). */
abstract class FinanceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function user(UserRole $role = UserRole::Admin, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'active' => true,
        ] + $attributes)->fresh();
    }

    protected function event(string $name = 'Festa Junina 2026'): Event
    {
        return Event::create([
            'name' => $name,
            'location' => 'Goiânia',
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-22',
            'active' => true,
        ]);
    }

    protected function sheet(?Event $event = null): FinanceSheet
    {
        return app(FinanceSheetProvider::class)->forEvent($event ?? $this->event());
    }

    protected function board(string $name = 'Orçamentos', bool $feedsFinance = false): Board
    {
        $board = Board::create(['name' => $name, 'feeds_finance' => $feedsFinance]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1, 'is_entry' => true]);

        return $board;
    }

    protected function card(Board $board, Event $event, array $attributes = []): Card
    {
        return Card::create([
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'event_id' => $event->id,
            'title' => 'Locação de som',
        ] + $attributes);
    }

    protected function categoria(string $nome = 'Estrutura Geral'): FornecedorCategoria
    {
        return FornecedorCategoria::firstOrCreate(['nome' => $nome], ['active' => true]);
    }

    protected function source(string $name = 'Caixa do Evento'): FinancePaymentSource
    {
        return FinancePaymentSource::firstOrCreate(['name' => $name], ['kind' => 'caixa', 'active' => true]);
    }
}
