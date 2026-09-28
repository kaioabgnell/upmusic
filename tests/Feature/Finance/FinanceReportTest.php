<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Models\FinanceCostItem;
use App\Models\FinanceSheet;
use App\Services\Finance\FinanceSummaryService;

/**
 * Aba RELATÓRIO e os números novos do "Custo por item" (specs/23 §7 e §8.2).
 */
class FinanceReportTest extends FinanceTestCase
{
    private function itemComPagamento(FinanceSheet $sheet, string $categoria, float $previsto, float $realizado, float $pago = 0): FinanceCostItem
    {
        $item = $sheet->costItems()->create([
            'description' => "Item {$categoria}",
            'fornecedor_categoria_id' => $this->categoria($categoria)->id,
            'unit_estimated_1' => $previsto,
            'unit_actual' => $realizado,
        ]);

        if ($pago > 0) {
            $item->payments()->create([
                'finance_payment_source_id' => $this->source()->id,
                'amount' => $pago,
                'paid_at' => '2026-06-10',
            ]);
        }

        return $item->refresh();
    }

    public function test_custo_por_item_traz_pago_e_falta_pagar_da_categoria(): void
    {
        $sheet = $this->sheet();
        $this->itemComPagamento($sheet, 'Limpeza', previsto: 1000, realizado: 1200, pago: 500);

        $linha = collect(app(FinanceSummaryService::class)->byCategory($sheet))->firstWhere('label', 'Limpeza');

        $this->assertEqualsWithDelta(1200, $linha['actual'], 0.01);
        $this->assertEqualsWithDelta(500, $linha['paid'], 0.01);
        $this->assertEqualsWithDelta(700, $linha['pending'], 0.01);
    }

    /**
     * O pago sai de subconsulta, não de join: com o join, cada pagamento multiplicaria os SUM de
     * previsto/realizado da categoria — dois pagamentos dobrariam o custo do evento na tela.
     */
    public function test_varios_pagamentos_na_mesma_linha_nao_inflam_previsto_e_realizado(): void
    {
        $sheet = $this->sheet();
        $item = $this->itemComPagamento($sheet, 'Som', previsto: 1000, realizado: 1000, pago: 400);
        $item->payments()->create([
            'finance_payment_source_id' => $this->source()->id,
            'amount' => 300,
            'paid_at' => '2026-06-20',
        ]);

        $linha = collect(app(FinanceSummaryService::class)->byCategory($sheet))->firstWhere('label', 'Som');

        $this->assertEqualsWithDelta(1000, $linha['estimated'], 0.01);
        $this->assertEqualsWithDelta(1000, $linha['actual'], 0.01);
        $this->assertEqualsWithDelta(700, $linha['paid'], 0.01);
    }

    /** `pct` é % de realização; `deviation_pct` é o quanto passou do teto. São números distintos. */
    public function test_desvio_percentual_separa_estouro_de_economia(): void
    {
        $sheet = $this->sheet();
        $this->itemComPagamento($sheet, 'Estrutura Geral', previsto: 1000, realizado: 1200);
        $this->itemComPagamento($sheet, 'Segurança', previsto: 1000, realizado: 800);

        $linhas = collect(app(FinanceSummaryService::class)->byCategory($sheet))->keyBy('label');

        $this->assertEqualsWithDelta(20, $linhas['Estrutura Geral']['deviation_pct'], 0.01);
        $this->assertEqualsWithDelta(120, $linhas['Estrutura Geral']['pct'], 0.01);
        $this->assertEqualsWithDelta(-20, $linhas['Segurança']['deviation_pct'], 0.01);
    }

    public function test_evolucao_de_caixa_agrupa_por_mes_e_acumula_o_saldo(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);

        $this->itemComPagamento($sheet, 'Limpeza', previsto: 1000, realizado: 1000, pago: 400);

        $sheet->revenues()->create([
            'category' => FinanceRevenueCategory::Patrocinio->value,
            'estimated_value' => 3000,
            'actual_value' => 3000,
            'received_value' => 3000,
            'received_at' => '2026-05-15',
        ]);

        $fluxo = collect(app(FinanceSummaryService::class)->cashFlow($sheet))->keyBy('month');

        $this->assertEqualsWithDelta(3000, $fluxo['2026-05']['in'], 0.01);
        $this->assertEqualsWithDelta(3000, $fluxo['2026-05']['balance'], 0.01);
        $this->assertEqualsWithDelta(400, $fluxo['2026-06']['out'], 0.01);
        // Saldo acumulado: 3.000 entraram em maio, 400 saíram em junho.
        $this->assertEqualsWithDelta(2600, $fluxo['2026-06']['balance'], 0.01);
    }

    public function test_relatorio_renderiza_os_quatro_graficos_do_evento(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $this->itemComPagamento($sheet, 'Limpeza', previsto: 1000, realizado: 1200, pago: 500);

        $html = $this->actingAs($this->user())
            ->get(route('finance.report', $event))
            ->assertOk()
            ->getContent();

        foreach (['grafico-previsto-realizado', 'grafico-categorias', 'grafico-orcado-gasto', 'grafico-caixa'] as $canvas) {
            $this->assertStringContainsString("id=\"{$canvas}\"", $html);
        }
    }

    public function test_aba_relatorio_aparece_na_barra_de_abas(): void
    {
        $event = $this->event();

        $html = $this->actingAs($this->user())
            ->get(route('finance.show', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('finance.report', $event), $html);
    }

    public function test_evento_sem_dados_mostra_estado_vazio_em_vez_de_grafico(): void
    {
        $event = $this->event();

        $html = $this->actingAs($this->user())
            ->get(route('finance.report', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sem dados para o relatório', $html);
        $this->assertStringNotContainsString('grafico-previsto-realizado', $html);
    }

    public function test_resumo_marca_acima_do_teto_e_mostra_pago_por_item(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $this->itemComPagamento($sheet, 'Limpeza', previsto: 1000, realizado: 1200, pago: 500);

        $html = $this->actingAs($this->user())
            ->get(route('finance.show', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Acima do teto', $html);
        // Total / Pago / Falta do valor real da categoria.
        $this->assertStringContainsString('R$ 500,00', $html);
        $this->assertStringContainsString('R$ 700,00', $html);
    }

    /** Dentro do teto não existe tag vermelha — o alarme só vale se for exceção. */
    public function test_resumo_dentro_do_teto_nao_marca_acima_do_teto(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $this->itemComPagamento($sheet, 'Limpeza', previsto: 1000, realizado: 800);

        $html = $this->actingAs($this->user())
            ->get(route('finance.show', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Acima do teto', $html);
    }

    /**
     * O filtro do "Custo por item" é client-side: as categorias já vêm renderizadas, então cada
     * linha carrega o próprio rótulo sem acento para o x-show comparar sem ida ao servidor.
     */
    public function test_custo_por_item_tem_campo_de_busca_por_item(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $this->itemComPagamento($sheet, 'Segurança', previsto: 1000, realizado: 900);
        $this->itemComPagamento($sheet, 'Limpeza', previsto: 500, realizado: 500);

        $html = $this->actingAs($this->user())
            ->get(route('finance.show', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('placeholder="Filtrar item"', $html);
        $this->assertStringContainsString('x-model="busca"', $html);

        // "Segurança" vira "seguranca" para quem digita sem acento também encontrar.
        $this->assertStringContainsString('seguranca', $html);
        $this->assertStringContainsString('limpeza', $html);
        $this->assertSame(2, substr_count($html, 'x-show="combina('));
    }

    /** A configuração saiu da tela, mas continua no arquivo para ser reativada. */
    public function test_configuracao_da_planilha_esta_comentada_e_nao_renderiza(): void
    {
        $event = $this->event();

        $html = $this->actingAs($this->user())
            ->get(route('finance.show', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Configuração da planilha', $html);
        $this->assertStringContainsString(
            'Configuração da planilha',
            file_get_contents(resource_path('views/financeiro/eventos/show.blade.php')),
        );
    }
}
