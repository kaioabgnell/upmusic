<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Services\Finance\FinanceExportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Nenhuma fórmula do XLSX pode se referir à própria célula: o Excel abre o arquivo com o aviso de
 * "referências circulares" e avisa que os valores podem estar errados.
 *
 * O caso real era a planilha sem linhas: `=SUM(B4:B3)` na linha 4. O intervalo invertido parece
 * vazio, mas o Excel o normaliza para `B3:B4` — que inclui a célula do próprio total. Bastava o
 * evento não ter receitas lançadas.
 */
class FinanceExportFormulasTest extends FinanceTestCase
{
    /** Varre todas as abas e falha na primeira fórmula que alcance a célula onde ela mora. */
    private function assertSemReferenciaCircular(Spreadsheet $book): void
    {
        foreach ($book->getAllSheets() as $ws) {
            foreach ($ws->getCoordinates() as $coordinate) {
                $formula = $ws->getCell($coordinate)->getValue();

                if (! is_string($formula) || ! str_starts_with($formula, '=')) {
                    continue;
                }

                preg_match_all('/([A-Z]+\d+):([A-Z]+\d+)/', $formula, $ranges, PREG_SET_ORDER);

                foreach ($ranges as $range) {
                    $this->assertFalse(
                        $this->contains($range[1], $range[2], $coordinate),
                        "A fórmula {$formula} em {$ws->getTitle()}!{$coordinate} inclui a própria célula.",
                    );
                }
            }
        }
    }

    /** Intervalo invertido conta: é assim que o Excel o interpreta. */
    private function contains(string $start, string $end, string $cell): bool
    {
        [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
        [$endColumn, $endRow] = Coordinate::coordinateFromString($end);
        [$column, $row] = Coordinate::coordinateFromString($cell);

        $columnIndex = Coordinate::columnIndexFromString($column);
        $columns = [Coordinate::columnIndexFromString($startColumn), Coordinate::columnIndexFromString($endColumn)];
        $rows = [(int) $startRow, (int) $endRow];

        return $columnIndex >= min($columns) && $columnIndex <= max($columns)
            && (int) $row >= min($rows) && (int) $row <= max($rows);
    }

    private function book(Event $event): Spreadsheet
    {
        return IOFactory::load(app(FinanceExportService::class)->toXlsx($this->sheet($event)->refresh()));
    }

    public function test_planilha_vazia_exporta_sem_referencia_circular(): void
    {
        $this->assertSemReferenciaCircular($this->book($this->event()));
    }

    /** Custos lançados, receitas ainda não — o cenário exato em que o aviso apareceu. */
    public function test_planilha_so_com_custos_exporta_sem_referencia_circular(): void
    {
        $event = $this->event();
        $this->sheet($event)->costItems()->create([
            'description' => 'Gerador 260KVA',
            'unit_estimated_1' => 1250.50,
            'unit_actual' => 1300,
        ]);

        $this->assertSemReferenciaCircular($this->book($event));
    }

    /** Evento novo: a planilha já vem com as receitas do modelo, mas nenhuma linha de custo. */
    public function test_total_sem_linhas_e_zero_e_nao_formula(): void
    {
        $custos = $this->book($this->event())->getSheetByName('CUSTOS');

        // Linha 8 é a primeira de dados e, sem custos, também a do TOTAL GERAL.
        $this->assertSame('TOTAL GERAL:', $custos->getCell('B8')->getValue());
        $this->assertEqualsWithDelta(0, $custos->getCell('I8')->getValue(), 0.001);
    }

    public function test_totais_continuam_somando_quando_ha_linhas(): void
    {
        $event = $this->event();
        $sheet = $this->sheet($event);
        $sheet->costItems()->create(['description' => 'Gerador', 'unit_estimated_1' => 100]);
        $sheet->costItems()->create(['description' => 'Tenda', 'unit_estimated_1' => 250]);

        $custos = $this->book($event)->getSheetByName('CUSTOS');

        // Duas linhas de item (8 e 9) e o total na 10, somando só o intervalo dos itens.
        $this->assertSame('=SUM(I8:I9)', $custos->getCell('I10')->getValue());
    }
}
