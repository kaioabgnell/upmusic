<?php

namespace Tests\Feature\FixedCosts;

use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\Card;
use App\Models\FixedCost;

/** Comando agendado `custos-fixos:gerar` (specs/27 §3.2). */
class GenerateFixedCostsCommandTest extends FixedCostTestCase
{
    public function test_gera_so_os_custos_ativos_do_modo_x_dias_antes(): void
    {
        $this->travelTo('2026-11-01');
        $board = $this->board();
        $empresa = $this->empresa();

        $lead = $this->makeCost($board, $empresa, [
            'name' => 'Água', 'first_due_date' => '2026-12-10', 'installments_total' => 3,
            'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 10,
        ]);
        $inactive = $this->makeCost($board, $empresa, [
            'name' => 'Luz', 'first_due_date' => '2026-12-10', 'installments_total' => 3,
            'generation_mode' => FixedCostGenerationMode::LeadDays->value, 'lead_days' => 10, 'active' => false,
        ]);
        $this->assertSame(0, Card::count());

        $this->artisan('custos-fixos:gerar', ['--date' => '2026-12-01'])
            ->expectsOutputToContain('1 card(s) de custo fixo gerado(s) em 01/12/2026.')
            ->assertSuccessful();

        $this->assertSame([1], $lead->fresh()->generatedNumbers()->all());
        $this->assertSame(0, $inactive->fresh()->generatedNumbers()->count());

        // Rodar de novo no mesmo dia não duplica; dois meses depois, gera as que faltam.
        $this->artisan('custos-fixos:gerar', ['--date' => '2026-12-01'])->assertSuccessful();
        $this->assertSame(1, Card::count());

        $this->artisan('custos-fixos:gerar', ['--date' => '2027-02-15'])->assertSuccessful();
        $this->assertSame([1, 2, 3], $lead->fresh()->generatedNumbers()->all());
    }

    public function test_data_invalida_e_recusada(): void
    {
        $this->artisan('custos-fixos:gerar', ['--date' => '01/12/2026'])->assertFailed();
        $this->assertSame(0, FixedCost::count());
    }
}
