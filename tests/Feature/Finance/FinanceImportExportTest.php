<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\ImportFinanceSpreadsheet;
use App\Domain\Enums\FinanceRevenueCategory;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use App\Services\Finance\FinanceExportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Migração da planilha e export no layout do modelo (specs/23 §12). O teste lê o ARQUIVO REAL de
 * referência — se o modelo mudar de forma, o teste avisa.
 */
class FinanceImportExportTest extends FinanceTestCase
{
    private function modelFile(): UploadedFile
    {
        $path = base_path('referencia/FINANCEIRO - MODELO (1).xlsx');

        if (! is_readable($path)) {
            $this->markTestSkipped('Arquivo de referência não disponível neste ambiente.');
        }

        // Cópia: o UploadedFile de teste move/consome o arquivo original em alguns fluxos.
        $copy = tempnam(sys_get_temp_dir(), 'modelo_').'.xlsx';
        copy($path, $copy);

        return new UploadedFile($copy, 'FINANCEIRO - MODELO.xlsx', null, null, true);
    }

    public function test_preview_do_arquivo_modelo_vazio_nao_traz_linhas_estruturais(): void
    {
        $preview = app(ImportFinanceSpreadsheet::class)->preview($this->modelFile());

        // O modelo em branco só tem o catálogo de itens (sem valores) — nada disso é gasto.
        $this->assertSame([], $preview['costs']);
        $this->assertSame([], $preview['revenues']);
    }

    public function test_importa_linhas_com_valores_mapeando_categoria_status_e_pagamentos(): void
    {
        $this->seed(\Database\Seeders\FinanceCatalogSeeder::class);

        $path = $this->buildFilledSpreadsheet();
        $preview = app(ImportFinanceSpreadsheet::class)->preview(
            new UploadedFile($path, 'planilha.xlsx', null, null, true)
        );

        $this->assertCount(1, $preview['costs']);
        $this->assertCount(1, $preview['revenues']);

        $sheet = $this->sheet();
        $counts = app(ImportFinanceSpreadsheet::class)->import($sheet, $preview['costs'], $preview['revenues'], $this->user());

        $this->assertSame(1, $counts['costs']);
        $this->assertSame(1, $counts['revenues']);
        $this->assertSame(1, $counts['payments']);

        $item = FinanceCostItem::sole()->refresh();
        $this->assertSame('TENDA 10X10', $item->description);
        $this->assertSame('Estrutura Geral', $item->categoria->nome);
        $this->assertSame('contrato_ok_falta_nota', $item->status->value);
        $this->assertSame('art_ok', $item->art_status->value);
        // Status veio do arquivo: a derivação automática não pode sobrescrever a decisão original.
        $this->assertFalse($item->status_auto);
        $this->assertEquals(3000, (float) $item->total_actual);   // 1500 x 2 x 1
        $this->assertEquals(2000, (float) $item->payments()->sum('amount'));

        // O bloco CONTROLE do arquivo é marcação textual, nunca documento — arquivo não veio junto.
        $this->assertSame(0, FinanceDocument::count());
        $this->assertStringContainsString('No arquivo original constava', $item->notes);

        $revenue = $sheet->revenues()->where('category', FinanceRevenueCategory::Patrocinio->value)->sole();
        $this->assertSame('Patrocinador A', $revenue->description);
        $this->assertEquals(5000, (float) $revenue->pending_value);   // 20.000 - 15.000
    }

    public function test_export_gera_as_tres_abas_no_layout_do_modelo(): void
    {
        $sheet = $this->sheet();
        $sheet->costItems()->create([
            'description' => 'Tenda 10x10', 'daily_count' => 1, 'quantity' => 1,
            'unit_estimated_1' => 1000, 'unit_actual' => 1100,
        ]);

        $path = app(FinanceExportService::class)->toXlsx($sheet->refresh());
        $book = IOFactory::createReader('Xlsx')->load($path);

        $this->assertSame(['RESUMO GERAL', 'RECEITAS', 'CUSTOS'], $book->getSheetNames());
        $this->assertSame('ITEM', $book->getSheetByName('CUSTOS')->getCell('A7')->getValue());
        $this->assertSame('Tenda 10x10', $book->getSheetByName('CUSTOS')->getCell('B8')->getValue());
        $this->assertSame('RECEITA', $book->getSheetByName('RECEITAS')->getCell('A3')->getValue());

        @unlink($path);
    }

    public function test_export_pela_rota_baixa_o_arquivo(): void
    {
        $event = $this->event();
        $this->sheet($event);

        $this->actingAs($this->user())
            ->get(route('finance.export', $event))
            ->assertOk()
            ->assertDownload();
    }

    public function test_tela_de_previa_do_import_renderiza_e_nao_grava_nada(): void
    {
        $this->seed(\Database\Seeders\FinanceCatalogSeeder::class);
        $event = $this->event();

        $this->actingAs($this->user())
            ->post(route('finance.import.preview', $event), [
                'file' => new UploadedFile($this->buildFilledSpreadsheet(), 'planilha.xlsx', null, null, true),
            ])
            ->assertOk()
            ->assertSee('TENDA 10X10')
            ->assertSee('Patrocinador A');

        // Pré-visualização é obrigatória e não grava: só o POST de confirmação escreve.
        $this->assertSame(0, FinanceCostItem::count());
    }

    /** Monta um arquivo no layout do modelo com uma linha de custo e uma receita preenchidas. */
    private function buildFilledSpreadsheet(): string
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->removeSheetByIndex(0);

        $costs = $book->createSheet();
        $costs->setTitle('CUSTOS');
        $costs->setCellValue('I6', 'PREVISTO 1');
        foreach ([
            'A7' => 'ITEM', 'B7' => 'DESCRIÇÃO', 'C7' => 'STATUS', 'D7' => 'ART', 'E7' => 'EMPRESA',
            'F7' => 'AUTORIZADO POR', 'G7' => 'DIÁRIAS', 'H7' => 'QUANT.', 'I7' => 'VALOR UNIT.',
            'O7' => 'CAIXA EVENTO', 'P7' => 'SÓCIO 1', 'Q7' => 'SÓCIO 2', 'R7' => 'TICKETEIRA', 'S7' => 'BAR',
            'V7' => 'ORÇAMENTO', 'W7' => 'CONTRATO', 'X7' => 'NOTA FISCAL', 'Y7' => 'COMPROVANTE',
            'Z7' => 'ART', 'AA7' => 'BOLETO',
        ] as $cell => $value) {
            $costs->setCellValue($cell, $value);
        }

        $costs->setCellValue('A8', 'ESTRUTURA GERAL');
        $costs->setCellValue('B8', 'TENDA 10X10');
        $costs->setCellValue('C8', 'CONTRATO OK | FALTA NOTA');
        $costs->setCellValue('D8', 'ART OK');
        $costs->setCellValue('E8', 'Locadora Silva');
        $costs->setCellValue('F8', 'Kaio');
        $costs->setCellValue('G8', 1);
        $costs->setCellValue('H8', 2);
        $costs->setCellValue('I8', 1400);
        $costs->setCellValue('M8', 1500);
        $costs->setCellValue('O8', 2000);
        $costs->setCellValue('V8', 'X');
        $costs->setCellValue('W8', 'X');

        $revenues = $book->createSheet();
        $revenues->setTitle('RECEITAS');
        $revenues->setCellValue('A3', 'RECEITA');
        $revenues->setCellValue('A4', 'PATROCÍNIO | Patrocinador A');
        $revenues->setCellValue('B4', 25000);
        $revenues->setCellValue('C4', 20000);
        $revenues->setCellValue('D4', 15000);
        $revenues->setCellValue('A5', 'TOTAL GERAL:');

        $path = tempnam(sys_get_temp_dir(), 'filled_').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return $path;
    }
}
