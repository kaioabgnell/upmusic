<?php

namespace Tests\Feature\Finance;

use App\Models\FinanceCostItem;
use App\Services\Finance\FinanceSummaryService;

/**
 * Colunas geradas e a regra do "Previsto 2" (specs/23 §4.3 e §7) — o coração numérico da planilha.
 */
class FinanceCostItemTest extends FinanceTestCase
{
    public function test_totais_sao_calculados_pelo_banco_nos_tres_cenarios(): void
    {
        $sheet = $this->sheet();

        $item = $sheet->costItems()->create([
            'description' => 'Brigadistas | Evento',
            'daily_count' => 3,
            'quantity' => 5,
            'unit_estimated_1' => 100,
            'unit_estimated_2' => 90,
            'unit_actual' => 110,
        ])->refresh();

        // TOTAL = VALOR UNIT. x QUANT. x DIÁRIAS, como as fórmulas J/L/N do arquivo modelo.
        $this->assertEquals(1500, (float) $item->total_estimated_1);
        $this->assertEquals(1350, (float) $item->total_estimated_2);
        $this->assertEquals(1650, (float) $item->total_actual);
    }

    public function test_alterar_quantidade_recalcula_os_totais_sem_intervencao_da_aplicacao(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create([
            'description' => 'Tenda 10x10',
            'daily_count' => 1,
            'quantity' => 2,
            'unit_estimated_1' => 500,
        ]);

        $item->update(['quantity' => 4]);

        $this->assertEquals(2000, (float) $item->refresh()->total_estimated_1);
    }

    public function test_linha_sem_previsto_2_continua_valendo_pelo_previsto_1(): void
    {
        $sheet = $this->sheet();
        $sheet->update(['uses_second_estimate' => true]);

        // Refinada na coleta de orçamentos: vale o Previsto 2.
        $sheet->costItems()->create([
            'description' => 'Refinada', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 1000, 'unit_estimated_2' => 800,
        ]);
        // Nunca refinada: continua valendo o Previsto 1.
        $sheet->costItems()->create([
            'description' => 'Original', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 300, 'unit_estimated_2' => null,
        ]);

        $summary = app(FinanceSummaryService::class)->summary($sheet->refresh());

        $this->assertEquals(1100, $summary['cost']['current_estimate']);   // 800 + 300
        $this->assertEquals(1300, $summary['cost']['estimated_1']);        // 1000 + 300
    }

    public function test_previsto_2_desligado_faz_o_resumo_usar_apenas_o_previsto_1(): void
    {
        $sheet = $this->sheet();
        $sheet->costItems()->create([
            'description' => 'Refinada', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 1000, 'unit_estimated_2' => 800,
        ]);

        $summary = app(FinanceSummaryService::class)->summary($sheet->refresh());

        $this->assertEquals(1000, $summary['cost']['current_estimate']);
    }

    public function test_realizado_nulo_nao_conta_como_zero_realizado(): void
    {
        $sheet = $this->sheet();
        $item = $sheet->costItems()->create([
            'description' => 'Ainda não aconteceu', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 500, 'unit_actual' => null,
        ])->refresh();

        // O total é 0 (não há valor), mas o campo unitário segue null — é o que distingue
        // "ainda não aconteceu" de "saiu de graça" na tela e nos alertas.
        $this->assertNull($item->unit_actual);
        $this->assertEquals(0, (float) $item->total_actual);

        $freeItem = $sheet->costItems()->create([
            'description' => 'Cortesia', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 200, 'unit_actual' => 0,
        ])->refresh();

        $this->assertNotNull($freeItem->unit_actual);
    }

    public function test_estimativa_vigente_da_linha_espelha_a_expressao_sql(): void
    {
        $sheet = $this->sheet();
        $sheet->costItems()->create([
            'description' => 'A', 'daily_count' => 2, 'quantity' => 2,
            'unit_estimated_1' => 10, 'unit_estimated_2' => 5,
        ]);

        $item = FinanceCostItem::sole();
        $sql = (float) FinanceCostItem::selectRaw(FinanceCostItem::currentEstimateSql().' as v')->value('v');

        $this->assertEquals($sql, $item->currentEstimate());
    }
}
