<?php

namespace Tests\Feature\Finance;

/**
 * Ajustes de UI da grade de Custos e da tela de Receitas: menu de contexto no Controle, máscara e
 * largura do "Vlr. unit. real.", tooltip de "pago a mais", e a mesma máscara nos valores da aba
 * Receitas.
 */
class FinanceCostsGridUiTest extends FinanceTestCase
{
    public function test_coluna_controle_vira_botao_resumo_com_menu(): void
    {
        $blade = file_get_contents(resource_path('views/financeiro/eventos/custos.blade.php'));

        // Um botão só por linha (com o total de documentos) abrindo um menu com a lista completa —
        // não mais até 9 ícones soltos ocupando a largura da coluna.
        $this->assertStringContainsString('controlOpen', $blade);
        $this->assertStringContainsString('fa-folder-open', $blade);
        $this->assertMatchesRegularExpression(
            '/<template x-for="chip in row\.documents\.filter\(c => c\.proof \|\| c\.count > 0\)" :key="chip\.kind">\s*<button type="button" @click="openDocuments\(row, chip\.kind\); controlOpen = false"/',
            $blade,
        );

        // Cada item do menu mostra "Nome (N)" — a quantidade junto do nome, não um número solto.
        $this->assertStringContainsString('x-text="`${chip.label} (${chip.count})`"', $blade);
    }

    /** Render real: dois tipos de documento na mesma linha, cada um com a própria contagem. */
    public function test_menu_do_controle_mostra_a_quantidade_de_cada_tipo(): void
    {
        $event = $this->event();
        $item = $this->sheet($event)->costItems()->create(['description' => 'Diária de som']);
        $item->documents()->createMany([
            ['kind' => \App\Domain\Enums\FinanceDocumentKind::NotaFiscal->value, 'path' => 'a', 'original_name' => 'nf1.pdf'],
            ['kind' => \App\Domain\Enums\FinanceDocumentKind::NotaFiscal->value, 'path' => 'b', 'original_name' => 'nf2.pdf'],
            ['kind' => \App\Domain\Enums\FinanceDocumentKind::Comprovante->value, 'path' => 'c', 'original_name' => 'comp.pdf'],
        ]);

        $rows = $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->viewData('rows');

        $chips = collect($rows)->firstWhere('id', $item->id)['documents'];
        $this->assertSame(2, collect($chips)->firstWhere('kind', 'nota_fiscal')['count']);
        $this->assertSame(1, collect($chips)->firstWhere('kind', 'comprovante')['count']);
    }

    public function test_vlr_unit_real_usa_mascara_de_centavos_e_coluna_maior(): void
    {
        $blade = file_get_contents(resource_path('views/financeiro/eventos/custos.blade.php'));

        $this->assertMatchesRegularExpression(
            '/:value="row\.unit_actual"\s+@input="row\.unit_actual = maskMoneyDigits\(\$event\.target\.value\); touch\(row\)"/',
            $blade,
        );
        $this->assertStringNotContainsString('x-model="row.unit_actual"', $blade);
        $this->assertStringContainsString('w-36 text-right" x-show="groups.actual">Vlr. unit. real.', $blade);

        // Os quatro pontos em que uma linha chega (ou volta) do servidor precisam normalizar —
        // senão o campo mostra "1234.5" (ponto, sem milhar) assim que o servidor responde.
        $js = file_get_contents(resource_path('js/finance.js'));
        $this->assertStringContainsString('function normalizeCostRow(row)', $js);
        $this->assertStringContainsString('unit_actual: moneyFromDecimal(row.unit_actual)', $js);
        $this->assertStringContainsString('rows: (cfg.rows ?? []).map(normalizeCostRow)', $js);
        $this->assertStringContainsString('Object.assign(row, normalizeCostRow(saved))', $js);
        $this->assertStringContainsString('this.rows.push(normalizeCostRow(created))', $js);
        $this->assertStringContainsString('normalizeCostRow(copy)', $js);
    }

    public function test_falta_pagar_mostra_pago_a_mais_so_no_tooltip(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $item = $sheet->costItems()->create(['description' => 'Pago a mais', 'unit_actual' => 100]);
        $item->payments()->create(['finance_payment_source_id' => $this->source()->id, 'amount' => 150, 'paid_at' => '2026-06-10']);

        $html = $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('(pago a mais)', $html);
        $this->assertStringContainsString(":title=\"row.pending < 0 ? 'Pago a mais' : null\"", $html);
    }

    public function test_campos_de_valor_das_receitas_usam_a_mascara_de_centavos(): void
    {
        $blade = file_get_contents(resource_path('views/financeiro/eventos/receitas.blade.php'));

        foreach (['estimated_value', 'actual_value', 'received_value'] as $campo) {
            $this->assertMatchesRegularExpression(
                '/:value="row\.'.$campo.'"\s+@input="row\.'.$campo.' = maskMoneyDigits\(\$event\.target\.value\); touch\(row\)"/',
                $blade,
            );
            $this->assertStringNotContainsString("x-model=\"row.{$campo}\"", $blade);
        }

        $js = file_get_contents(resource_path('js/finance.js'));
        $this->assertStringContainsString('function normalizeRevenueRow(row)', $js);
    }

    /**
     * `normalizeRevenueRow` precisa ser aplicado nos três pontos em que uma linha de receita chega
     * do servidor — carga inicial, autosave e "Adicionar receita" — senão a linha volta a mostrar
     * "1234.5" (ponto, sem milhar) assim que o servidor responde.
     */
    public function test_normalizacao_de_receita_cobre_carga_autosave_e_nova_linha(): void
    {
        $js = file_get_contents(resource_path('js/finance.js'));

        $this->assertStringContainsString('rows: (cfg.rows ?? []).map(normalizeRevenueRow)', $js);
        $this->assertStringContainsString('Object.assign(row, normalizeRevenueRow(saved))', $js);
        $this->assertStringContainsString('this.rows.push(normalizeRevenueRow(created))', $js);
    }
}
