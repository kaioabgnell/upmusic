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

    /** O filtro de ART saiu da barra de filtros — a coluna por linha e o "Alterar ART…" em massa ficam. */
    public function test_filtro_de_art_foi_removido_mas_coluna_e_bulk_continuam(): void
    {
        $event = $this->event();

        $html = $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="art_status"', $html);
        $this->assertStringContainsString('>ART<', $html);
        $this->assertStringContainsString("bulk('art_status'", $html);
    }

    /**
     * O filtro de Empresa é seleção múltipla por checkbox (`fornecedor_id[]`) — mesmo padrão de
     * `RentalOrderController` para filtros de várias opções num GET simples (não existe `multiple`
     * de `<select>` nem componente de dropdown com checkbox pronto no projeto; o visual é adaptado
     * do dropdown de busca de fornecedor do card-panel, mas com `@checked` server-rendered).
     */
    public function test_filtro_de_empresa_e_multipla_escolha_por_checkbox(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $fornecedorA = \App\Models\Fornecedor::create([
            'type' => \App\Domain\Enums\PessoaTipo::PJ->value, 'name' => 'Som & Luz', 'document' => '11222333000181', 'active' => true,
        ]);
        $fornecedorB = \App\Models\Fornecedor::create([
            'type' => \App\Domain\Enums\PessoaTipo::PJ->value, 'name' => 'Estruturas XYZ', 'document' => '11444777000161', 'active' => true,
        ]);
        $fornecedorC = \App\Models\Fornecedor::create([
            'type' => \App\Domain\Enums\PessoaTipo::PJ->value, 'name' => 'Palco Total', 'document' => '11555666000171', 'active' => true,
        ]);
        $sheet->costItems()->create(['description' => 'Item A', 'fornecedor_id' => $fornecedorA->id]);
        $sheet->costItems()->create(['description' => 'Item B', 'fornecedor_id' => $fornecedorB->id]);
        $sheet->costItems()->create(['description' => 'Item C', 'fornecedor_id' => $fornecedorC->id]);

        $html = $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="fornecedor_id[]"', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('>Empresa<', $html);

        // Marca A e B ao mesmo tempo — precisa trazer os dois, excluir C.
        $rows = $this->actingAs($this->user())
            ->get(route('finance.costs.index', [
                'evento' => $event,
                'fornecedor_id' => [$fornecedorA->id, $fornecedorB->id],
            ]))
            ->assertOk()
            ->viewData('rows');

        $this->assertCount(2, $rows);
        $this->assertSame(['Item A', 'Item B'], $rows->pluck('description')->sort()->values()->all());

        // Nenhum marcado: volta todo mundo (sem filtro), não "filtra por nada".
        $rows = $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->viewData('rows');

        $this->assertCount(3, $rows);
    }

    public function test_filtro_de_total_realizado_tem_mascara_e_filtra_pelo_valor_exato(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $sheet->costItems()->create(['description' => 'Bate', 'unit_actual' => 1500]);
        $sheet->costItems()->create(['description' => 'Não bate', 'unit_actual' => 900]);

        $html = $this->actingAs($this->user())
            ->get(route('finance.costs.index', $event))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="total_actual"', $html);
        $this->assertStringContainsString('window.maskMoneyDigits($event.target.value)', $html);
        $this->assertStringContainsString('>Total real.<', $html);

        $rows = $this->actingAs($this->user())
            ->get(route('finance.costs.index', ['evento' => $event, 'total_actual' => '1.500,00']))
            ->assertOk()
            ->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('Bate', $rows->first()['description']);
    }

    /** Campo vazio (ou inválido) não pode zerar a grade — é "sem filtro", não "filtra por nada". */
    public function test_filtro_de_total_realizado_vazio_nao_filtra_nada(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $sheet->costItems()->create(['description' => 'Item único', 'unit_actual' => 1500]);

        $rows = $this->actingAs($this->user())
            ->get(route('finance.costs.index', ['evento' => $event, 'total_actual' => '']))
            ->assertOk()
            ->viewData('rows');

        $this->assertCount(1, $rows);
    }
}
