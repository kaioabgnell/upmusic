<?php

namespace Tests\Feature\FixedCosts;

use App\Actions\FixedCosts\CreateFixedCost;
use App\Domain\Enums\FixedCostGenerationMode;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Empresa;
use App\Models\Event;
use App\Models\FixedCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base comum dos testes de Custos fixos (specs/27). */
abstract class FixedCostTestCase extends TestCase
{
    use RefreshDatabase;

    protected function user(UserRole $role = UserRole::Admin): User
    {
        return User::factory()->create(['role' => $role->value, 'active' => true])->fresh();
    }

    /** Coordenador restrito por evento (specs/20). */
    protected function scopedCoordinator(): User
    {
        $user = $this->user(UserRole::Coordenador);
        $user->events()->attach(Event::create([
            'name' => 'Festa', 'location' => 'Goiânia', 'start_date' => '2026-06-20', 'end_date' => '2026-06-22', 'active' => true,
        ])->id);

        return $user->fresh();
    }

    protected function empresa(string $name = 'Up Music Ltda', string $document = '11222333000181'): Empresa
    {
        return Empresa::firstOrCreate(['document' => $document], ['corporate_name' => $name, 'type' => 'PJ', 'active' => true]);
    }

    protected function board(string $name = 'Custos Fixos', bool $fixedCosts = true): Board
    {
        $board = Board::create(['name' => $name, 'fixed_costs' => $fixedCosts, 'active' => true]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'A pagar', 'position' => 0, 'is_entry' => true]);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Pago', 'position' => 1, 'is_final' => true]);

        return $board->fresh();
    }

    /** Dados válidos do formulário, sobrescrevíveis. */
    protected function payload(Board $board, Empresa $empresa, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Aluguel',
            'empresa_id' => $empresa->id,
            'installment_value' => '1.500,00',
            'installments_total' => 12,
            'start_installment' => 1,
            'first_due_date' => '2027-01-31',
            'board_id' => $board->id,
            'board_column_id' => '',
            'generation_mode' => FixedCostGenerationMode::AllUpfront->value,
            'lead_days' => '',
            'description' => 'Sala comercial',
            'active' => '1',
        ];
    }

    /** Cria direto pela Action (sem HTTP), gerando o que for devido. */
    protected function makeCost(Board $board, Empresa $empresa, array $attributes = []): FixedCost
    {
        [$cost] = app(CreateFixedCost::class)->execute($attributes + [
            'name' => 'Aluguel',
            'empresa_id' => $empresa->id,
            'installment_value' => 1500,
            'installments_total' => 12,
            'start_installment' => 1,
            'first_due_date' => '2027-01-31',
            'board_id' => $board->id,
            'generation_mode' => FixedCostGenerationMode::AllUpfront->value,
            'lead_days' => null,
            'active' => true,
        ]);

        return $cost->fresh();
    }
}
