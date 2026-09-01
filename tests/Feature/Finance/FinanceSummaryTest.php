<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Services\Finance\FinanceSummaryService;

/** RESUMO GERAL: receita, custo, resultado e acerto de sócios (specs/23 §7 e §8.2). */
class FinanceSummaryTest extends FinanceTestCase
{
    public function test_planilha_nasce_com_as_linhas_de_receita_do_modelo(): void
    {
        $sheet = $this->sheet();

        $this->assertSame(count(FinanceRevenueCategory::seedRows()), $sheet->revenues()->count());
        $this->assertSame(0, $sheet->revenues()->where('category', FinanceRevenueCategory::Patrocinio->value)->count());
    }

    public function test_resultado_previsto_e_realizado(): void
    {
        $sheet = $this->sheet();

        $sheet->revenues()->create([
            'category' => FinanceRevenueCategory::Patrocinio->value,
            'description' => 'Patrocinador A',
            'estimated_value' => 50000,
            'actual_value' => 45000,
            'received_value' => 30000,
        ]);

        $sheet->costItems()->create([
            'description' => 'Estrutura', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 20000, 'unit_actual' => 22000,
        ]);

        $summary = app(FinanceSummaryService::class)->summary($sheet->refresh());

        $this->assertEquals(30000, $summary['result']['estimated']);   // 50.000 - 20.000
        $this->assertEquals(23000, $summary['result']['actual']);      // 45.000 - 22.000
        $this->assertEquals(15000, $summary['revenue']['pending']);    // falta receber (coluna gerada)
    }

    public function test_custo_por_categoria_agrupa_e_ordena_pelo_previsto(): void
    {
        $sheet = $this->sheet();
        $rh = $this->categoria('RH');
        $estrutura = $this->categoria('Estrutura Geral');

        $sheet->costItems()->create([
            'description' => 'Brigadistas', 'fornecedor_categoria_id' => $rh->id,
            'daily_count' => 1, 'quantity' => 1, 'unit_estimated_1' => 1000,
        ]);
        $sheet->costItems()->create([
            'description' => 'Tenda', 'fornecedor_categoria_id' => $estrutura->id,
            'daily_count' => 1, 'quantity' => 1, 'unit_estimated_1' => 5000,
        ]);
        $sheet->costItems()->create(['description' => 'Sem categoria', 'unit_estimated_1' => 10]);

        $rows = app(FinanceSummaryService::class)->byCategory($sheet->refresh());

        $this->assertSame('Estrutura Geral', $rows[0]['label']);
        $this->assertEquals(5000, $rows[0]['estimated']);
        $this->assertSame('Sem categoria', $rows[2]['label']);
    }

    public function test_acerto_de_socios_usa_o_resultado_realizado_quando_nao_e_manual(): void
    {
        $sheet = $this->sheet();
        $sheet->settlements()->create(['partner_name' => 'Sócio A', 'percentage' => 60]);
        $sheet->settlements()->create(['partner_name' => 'Sócio B', 'percentage' => 40, 'manual_amount' => true, 'amount' => 1234]);

        $rows = app(FinanceSummaryService::class)->settlements($sheet, 10000);

        $this->assertEquals(6000, $rows[0]['amount']);
        $this->assertEquals(1234, $rows[1]['amount']);   // valor digitado à mão é preservado
    }

    public function test_percentual_de_socios_diferente_de_cem_vira_alerta(): void
    {
        $sheet = $this->sheet();
        $sheet->settlements()->create(['partner_name' => 'Sócio A', 'percentage' => 60]);

        $alerts = collect(app(FinanceSummaryService::class)->alerts($sheet));

        $this->assertTrue($alerts->contains(fn ($a) => str_contains($a['text'], 'não 100%')));
    }

    public function test_linha_realizada_sem_comprovante_vira_alerta(): void
    {
        $sheet = $this->sheet();
        $sheet->costItems()->create([
            'description' => 'Frete', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 100, 'unit_actual' => 100,
        ]);

        $alerts = collect(app(FinanceSummaryService::class)->alerts($sheet->refresh()));

        $this->assertTrue($alerts->contains(fn ($a) => str_contains($a['text'], 'sem comprovante')));
    }

    public function test_totais_de_varias_planilhas_em_lote(): void
    {
        $a = $this->sheet($this->event('Evento A'));
        $b = $this->sheet($this->event('Evento B'));

        $a->costItems()->create(['description' => 'X', 'daily_count' => 1, 'quantity' => 1, 'unit_estimated_1' => 100]);
        $b->costItems()->create(['description' => 'Y', 'daily_count' => 1, 'quantity' => 1, 'unit_estimated_1' => 250]);

        $totals = app(FinanceSummaryService::class)->totalsForSheets([$a->id, $b->id]);

        $this->assertEquals(100, $totals[$a->id]['cost_estimated']);
        $this->assertEquals(250, $totals[$b->id]['cost_estimated']);
    }
}
