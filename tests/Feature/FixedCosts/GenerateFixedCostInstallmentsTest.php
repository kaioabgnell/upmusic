<?php

namespace Tests\Feature\FixedCosts;

use App\Actions\FixedCosts\GenerateFixedCostInstallments;
use App\Domain\Enums\FixedCostGenerationMode;
use App\Models\Card;
use App\Models\FixedCostInstallment;
use Carbon\CarbonImmutable;

/** Geração das parcelas (specs/27 §3) e renovação/reativação (§5). */
class GenerateFixedCostInstallmentsTest extends FixedCostTestCase
{
    private function leadCost(array $attributes = [])
    {
        return $this->makeCost($this->board(), $this->empresa(), $attributes + [
            'name' => 'Empréstimo',
            'installments_total' => 5,
            'start_installment' => 3,
            'first_due_date' => '2026-12-10',
            'generation_mode' => FixedCostGenerationMode::LeadDays->value,
            'lead_days' => 10,
        ]);
    }

    public function test_modo_x_dias_antes_gera_so_o_que_entrou_na_janela(): void
    {
        // 30/11 + 10 dias = 10/12: a parcela 3 (vence 10/12) entra; a 4 (10/01) ainda não.
        $this->travelTo('2026-11-30');
        $cost = $this->leadCost();

        $this->assertSame([3], $cost->fresh()->generatedNumbers()->all());
        $card = Card::firstOrFail();
        $this->assertSame('2026-12-10', $card->due_date->toDateString());

        // Um dia antes da janela, nada.
        $this->travelTo('2026-11-29');
        $other = $this->leadCost(['name' => 'Outro', 'first_due_date' => '2026-12-10']);
        $this->assertSame(0, $other->fresh()->generatedNumbers()->count());
    }

    public function test_atrasadas_tambem_viram_card_e_rodar_de_novo_nao_duplica(): void
    {
        $this->travelTo('2026-11-18');
        $cost = $this->leadCost(['first_due_date' => '2026-12-10', 'active' => false]);
        $cost->update(['active' => true]);

        $generate = app(GenerateFixedCostInstallments::class);
        // Em 15/02/2027: parcelas 3 (10/12), 4 (10/01) e 5 (10/02) já venceram ou entraram na janela.
        $this->assertSame(3, $generate->execute($cost, null, CarbonImmutable::parse('2027-02-15')));
        $this->assertSame(0, $generate->execute($cost->fresh(), null, CarbonImmutable::parse('2027-02-15')));
        $this->assertSame(3, Card::count());
    }

    public function test_card_excluido_nao_e_recriado(): void
    {
        $this->travelTo('2026-11-30');
        $cost = $this->leadCost();
        Card::firstOrFail()->delete();

        app(GenerateFixedCostInstallments::class)->execute($cost->fresh(), null, CarbonImmutable::parse('2026-12-05'));

        $this->assertSame(0, Card::count());
        $this->assertSame(1, FixedCostInstallment::whereNotNull('generated_at')->count());
    }

    public function test_inativo_nao_gera_e_reativar_gera_o_que_entrou_na_janela(): void
    {
        $admin = $this->user();
        $this->travelTo('2026-11-01');
        $cost = $this->leadCost(['active' => false]);

        $this->travelTo('2027-01-05');
        $this->assertSame(0, app(GenerateFixedCostInstallments::class)->execute($cost));

        $this->actingAs($admin)->patch(route('fixed-costs.toggle-active', $cost))->assertSessionHas('success');

        // Parcela 3 (10/12) atrasada e 4 (10/01) dentro da janela de 10 dias.
        $this->assertTrue($cost->fresh()->active);
        $this->assertSame([3, 4], $cost->fresh()->generatedNumbers()->all());
    }

    public function test_gerar_proxima_agora_ignora_a_janela_e_gera_so_uma(): void
    {
        $admin = $this->user();
        $this->travelTo('2026-11-01');
        $cost = $this->leadCost();
        $this->assertSame(0, Card::count());

        $this->actingAs($admin)->post(route('fixed-costs.generate-next', $cost))
            ->assertSessionHas('success', 'Parcela 3 de 5 gerada.');
        $this->actingAs($admin)->post(route('fixed-costs.generate-next', $cost))
            ->assertSessionHas('success', 'Parcela 4 de 5 gerada.');

        $this->assertSame([3, 4], $cost->fresh()->generatedNumbers()->all());
    }

    public function test_renovar_soma_parcelas_e_atualiza_o_total_dos_selos(): void
    {
        $admin = $this->user();
        $board = $this->board();
        $cost = $this->makeCost($board, $this->empresa(), ['installments_total' => 12]);

        $this->actingAs($admin)->post(route('fixed-costs.renew', $cost), ['extra' => 12])
            ->assertSessionHas('success');

        $cost->refresh();
        $this->assertSame(24, $cost->installments_total);
        $this->assertSame(24, Card::count());

        $twelve = FixedCostInstallment::where('number', 12)->firstOrFail();
        $thirteen = FixedCostInstallment::where('number', 13)->firstOrFail();
        $this->assertSame('2027-12-31', $twelve->due_date->toDateString());
        $this->assertSame('2028-01-31', $thirteen->due_date->toDateString());

        $badges = collect($this->actingAs($admin)->getJson(route('boards.kanban.data', $board))->assertOk()->json('columns.0.cards'))
            ->pluck('installment.total')->unique()->values()->all();
        $this->assertSame([24], $badges);
    }

    public function test_vencimento_respeita_fim_de_mes_e_ano_bissexto(): void
    {
        $cost = $this->makeCost($this->board(), $this->empresa(), ['first_due_date' => '2028-01-31', 'installments_total' => 4]);

        $this->assertSame(
            ['2028-01-31', '2028-02-29', '2028-03-31', '2028-04-30'],
            $cost->installments()->get()->map(fn ($i) => $i->due_date->toDateString())->all()
        );
    }
}
