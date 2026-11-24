<?php

namespace Tests\Feature\Finance;

use App\Domain\Enums\BankAccountType;
use App\Domain\Enums\FinanceRevenueCategory;
use App\Models\BankAccount;
use App\Models\CostCenter;
use App\Models\FinanceCostItem;
use App\Models\FinanceSheet;
use App\Services\Finance\CostItemFilter;
use App\Services\Finance\FinanceExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Filtro por conta bancária na aba CUSTOS, e os filtros da aba valendo na exportação e no RELATÓRIO
 * (App\Services\Finance\CostItemFilter).
 */
class FinanceCostFiltersTest extends FinanceTestCase
{
    private function account(string $name): BankAccount
    {
        return BankAccount::create([
            'bank' => '341 - Itaú Unibanco', 'name' => $name, 'agency' => '1234',
            'account_number' => (string) random_int(10000, 99999), 'account_type' => BankAccountType::Corrente, 'active' => true,
        ]);
    }

    private function item(FinanceSheet $sheet, string $description, float $actual, ?BankAccount $paidFrom = null, float $paid = 0, array $attributes = []): FinanceCostItem
    {
        $item = $sheet->costItems()->create($attributes + [
            'description' => $description,
            'fornecedor_categoria_id' => $this->categoria()->id,
            'unit_estimated_1' => $actual,
            'unit_actual' => $actual,
        ]);

        if ($paid > 0) {
            $item->payments()->create([
                'finance_payment_source_id' => $this->source()->id,
                'bank_account_id' => $paidFrom?->id,
                'amount' => $paid,
                'paid_at' => '2026-06-10',
            ]);
        }

        return $item;
    }

    /** Cenário: Som pago pelo Itaú, Palco pelo Bradesco, Luz sem conta no pagamento, Banner sem pagamento. */
    private function scenario(): array
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $itau = $this->account('Operacional Itaú');
        $bradesco = $this->account('Bradesco Eventos');

        $this->item($sheet, 'Som', 1000, $itau, 400);
        $this->item($sheet, 'Palco', 2000, $bradesco, 2000);
        $this->item($sheet, 'Luz', 500, null, 100);
        $this->item($sheet, 'Banner', 300);

        return [$event, $sheet, $itau, $bradesco];
    }

    public function test_filtro_de_conta_bancaria_acha_as_linhas_pelos_pagamentos(): void
    {
        [$event, , $itau, $bradesco] = $this->scenario();

        $this->assertSame(['Som'], $this->costRows($event, ['bank_account_id' => [$itau->id]])->pluck('description')->all());
        $this->assertSame(['Luz'], $this->costRows($event, ['bank_account_id' => ['none']])->pluck('description')->all());
        $this->assertEqualsCanonicalizing(
            ['Som', 'Palco', 'Luz'],
            $this->costRows($event, ['bank_account_id' => [$itau->id, $bradesco->id, 'none']])->pluck('description')->all()
        );

        // Rodapé respeita o filtro.
        $footer = $this->actingAs($this->user())
            ->getJson(route('finance.costs.rows', ['evento' => $event, 'bank_account_id' => [$bradesco->id]]))
            ->json('footer');
        $this->assertSame(1, $footer['total']);
        $this->assertEqualsWithDelta(2000, $footer['actual'], 0.01);
    }

    public function test_tela_de_custos_tem_o_filtro_e_exporta_com_os_filtros(): void
    {
        [$event, , $itau] = $this->scenario();

        $html = $this->actingAs($this->user())
            ->get(route('finance.costs.index', ['evento' => $event, 'bank_account_id' => [$itau->id]]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Conta bancária', $html);
        $this->assertStringContainsString('Pagamento sem conta', $html);
        $this->assertStringContainsString('Exportar filtrado', $html);
        $this->assertStringContainsString(
            e(route('finance.export', ['evento' => $event, 'bank_account_id' => [$itau->id]])),
            $html
        );

        // Formulário enviado vazio não conta como filtro.
        $this->actingAs($this->user())
            ->get(route('finance.costs.index', ['evento' => $event, 'search' => '', 'status' => '']))
            ->assertOk()->assertDontSee('Exportar filtrado');
    }

    public function test_exportacao_filtrada_traz_so_as_linhas_do_filtro_e_o_resumo_delas(): void
    {
        [$event, $sheet, $itau] = $this->scenario();
        $settlement = $sheet->settlements()->create(['partner_name' => 'Sócio A', 'percentage' => 100, 'amount' => 0]);

        $path = app(FinanceExportService::class)->toXlsx($sheet, CostItemFilter::fromArray(['bank_account_id' => [$itau->id]]));
        $book = IOFactory::createReader('Xlsx')->load($path);
        @unlink($path);

        $custos = $book->getSheetByName('CUSTOS');
        $this->assertSame('Som', $custos->getCell('B8')->getValue());
        $this->assertSame('TOTAL GERAL:', $custos->getCell('B9')->getValue());
        $this->assertStringContainsString('Conta bancária: Operacional Itaú', (string) $custos->getCell('B5')->getValue());

        $resumo = $book->getSheetByName('RESUMO GERAL');
        $this->assertEqualsWithDelta(1000, $resumo->getCell('F5')->getValue(), 0.01);   // custo realizado só do Som
        $this->assertEqualsWithDelta(400, $resumo->getCell('G11')->getValue(), 0.01);   // pago
        $this->assertSame('FILTROS:', $resumo->getCell('B8')->getValue());
        // Acerto de sócios não sai no recorte — e não é recalculado/gravado por ele.
        $this->assertStringContainsString('Só na exportação sem filtros', (string) $resumo->getCell('F16')->getValue());
        $this->assertEqualsWithDelta(0, (float) $settlement->fresh()->amount, 0.01);

        // Pela rota: mesmo recorte e nome do arquivo avisando.
        $this->actingAs($this->user())
            ->get(route('finance.export', ['evento' => $event, 'bank_account_id' => [$itau->id]]))
            ->assertOk()
            ->assertDownload('FINANCEIRO - FESTA JUNINA 2026 (FILTRADO).xlsx');
    }

    public function test_receitas_seguem_so_centro_de_custo_e_conta_bancaria(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $marketing = CostCenter::create(['name' => 'Marketing', 'active' => true]);
        $producao = CostCenter::create(['name' => 'Produção', 'active' => true]);

        $this->item($sheet, 'Som', 1000, attributes: ['cost_center_id' => $marketing->id]);
        $this->item($sheet, 'Palco', 2000, attributes: ['cost_center_id' => $producao->id]);
        foreach ([[$marketing, 5000], [$producao, 7000]] as [$center, $value]) {
            $sheet->revenues()->create([
                'category' => FinanceRevenueCategory::Patrocinio->value, 'cost_center_id' => $center->id,
                'estimated_value' => $value, 'actual_value' => $value, 'received_value' => $value, 'received_at' => '2026-05-15',
            ]);
        }

        $path = app(FinanceExportService::class)->toXlsx($sheet, CostItemFilter::fromArray(['cost_center_id' => [$marketing->id]]));
        $book = IOFactory::createReader('Xlsx')->load($path);
        @unlink($path);

        $receitas = $book->getSheetByName('RECEITAS');
        $this->assertEqualsWithDelta(5000, $receitas->getCell('B4')->getValue(), 0.01);
        $this->assertSame('TOTAL GERAL:', $receitas->getCell('A5')->getValue());
        $this->assertStringContainsString('Centro de custo: Marketing', (string) $receitas->getCell('A2')->getValue());

        $resumo = $book->getSheetByName('RESUMO GERAL');
        $this->assertEqualsWithDelta(5000 - 1000, $resumo->getCell('F6')->getValue(), 0.01); // resultado do recorte

        // Filtro só de custos: receitas inteiras, com o aviso.
        $path = app(FinanceExportService::class)->toXlsx($sheet, CostItemFilter::fromArray(['search' => 'Som']));
        $receitas = IOFactory::createReader('Xlsx')->load($path)->getSheetByName('RECEITAS');
        @unlink($path);
        $totalRow = 4 + $sheet->revenues()->count(); // todas as receitas da planilha (inclusive as padrão)
        $this->assertSame('TOTAL GERAL:', $receitas->getCell("A{$totalRow}")->getValue());
        $this->assertStringContainsString('só de custos', (string) $receitas->getCell('A2')->getValue());
    }

    public function test_exportacao_sem_filtro_continua_igual(): void
    {
        [, $sheet] = $this->scenario();

        $path = app(FinanceExportService::class)->toXlsx($sheet, CostItemFilter::fromArray(['search' => '', 'status' => '']));
        $book = IOFactory::createReader('Xlsx')->load($path);
        @unlink($path);

        $this->assertNull($book->getSheetByName('CUSTOS')->getCell('A5')->getValue());
        $this->assertNull($book->getSheetByName('RESUMO GERAL')->getCell('B8')->getValue());
        $this->assertSame('TOTAL GERAL:', $book->getSheetByName('CUSTOS')->getCell('B12')->getValue()); // 4 linhas (8 a 11)
        $this->assertSame('FINANCEIRO - FESTA JUNINA 2026.xlsx', app(FinanceExportService::class)->filename($sheet, CostItemFilter::fromArray([])));
    }

    public function test_relatorio_aplica_os_mesmos_filtros_e_exporta_o_recorte(): void
    {
        [$event, , , $bradesco] = $this->scenario();

        $response = $this->actingAs($this->user())
            ->get(route('finance.report', ['evento' => $event, 'bank_account_id' => [$bradesco->id]]))
            ->assertOk();

        $this->assertEqualsWithDelta(2000, $response->viewData('summary')['cost']['actual'], 0.01);
        $this->assertEqualsWithDelta(2000, collect($response->viewData('cashFlow'))->sum('out'), 0.01);

        $html = $response->getContent();
        $this->assertStringContainsString('Relatório filtrado:', $html);
        $this->assertStringContainsString('Conta bancária: Bradesco Eventos', $html);
        $this->assertStringContainsString('Pagamento sem conta', $html);
        $this->assertStringContainsString(e(route('finance.export', ['evento' => $event, 'bank_account_id' => [$bradesco->id]])), $html);

        // Sem filtro, o relatório é o do evento inteiro.
        $this->assertEqualsWithDelta(
            3800,
            $this->actingAs($this->user())->get(route('finance.report', $event))->viewData('summary')['cost']['actual'],
            0.01
        );
    }

    public function test_relatorio_com_filtro_sem_resultado_mostra_estado_vazio_proprio(): void
    {
        [$event] = $this->scenario();

        $this->actingAs($this->user())
            ->get(route('finance.report', ['evento' => $event, 'search' => 'nada-com-isso']))
            ->assertOk()
            ->assertSee('Nenhum dado com esses filtros')
            ->assertDontSee('grafico-previsto-realizado');
    }

    /** Barra com os quatro filtros do dia a dia; os demais no painel "Busca avançada" — nas duas telas. */
    public function test_barra_tem_quatro_filtros_e_o_painel_tem_os_demais(): void
    {
        [$event] = $this->scenario();

        foreach ([route('finance.costs.index', $event), route('finance.report', $event)] as $url) {
            $html = $this->actingAs($this->user())->get($url)->assertOk()->getContent();

            foreach (['Buscar descrição', 'Empresa', 'Centro de custo', 'Conta bancária', 'Busca avançada', 'id="busca-avancada"'] as $text) {
                $this->assertStringContainsString($text, $html, "{$url}: {$text}");
            }
            foreach (['categoria_id', 'status', 'source_id', 'total_actual', 'sem_comprovante', 'falta_pagar', 'pago_a_maior'] as $field) {
                $this->assertStringContainsString("name=\"{$field}\"", $html, "{$url}: {$field}");
            }
            // Sem filtro avançado ativo, nada de etiquetas.
            $this->assertStringNotContainsString('Remover este filtro', $html);
        }
    }

    /** Filtro avançado ativo não fica escondido no painel: vira etiqueta removível e conta no botão. */
    public function test_filtros_avancados_ativos_viram_etiquetas_removiveis(): void
    {
        [$event] = $this->scenario();
        $query = ['evento' => $event, 'status' => 'orcamento', 'falta_pagar' => '1', 'search' => 'Som'];

        foreach (['finance.costs.index', 'finance.report'] as $route) {
            $html = $this->actingAs($this->user())->get(route($route, $query))->assertOk()->getContent();

            $this->assertStringContainsString('Status: Orçamento', $html);
            $this->assertStringContainsString('Falta pagar', $html);
            $this->assertStringContainsString('2 filtro(s) avançado(s) ativo(s)', $html);
            // Remover "Falta pagar" mantém os demais filtros.
            $this->assertStringContainsString(e(route($route, ['evento' => $event, 'status' => 'orcamento', 'search' => 'Som'])), $html);
        }
    }
}
