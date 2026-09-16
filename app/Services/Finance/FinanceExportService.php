<?php

namespace App\Services\Finance;

use App\Domain\Enums\FinanceDocumentKind;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use App\Models\FinancePaymentSource;
use App\Models\FinanceSheet;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta a planilha do evento no LAYOUT DO ARQUIVO MODELO (specs/23 §12): mesmas abas, mesmas
 * colunas, mesma ordem — para mandar à contabilidade e aos sócios sem obrigar ninguém a entrar no
 * sistema. É via de mão única: o arquivo exportado não volta a ser fonte da verdade.
 */
class FinanceExportService
{
    private const HEADER_FILL = 'FF0A0A0A';

    public function __construct(private FinanceSummaryService $summary) {}

    /** @return string caminho do arquivo temporário gerado */
    public function toXlsx(FinanceSheet $sheet): string
    {
        $sheet->loadMissing('event');

        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        $this->buildSummary($book, $sheet);
        $this->buildRevenues($book, $sheet);
        $this->buildCosts($book, $sheet);

        $book->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'finance_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function filename(FinanceSheet $sheet): string
    {
        $name = preg_replace('/[^A-Za-z0-9 _-]/', '', $sheet->event?->name ?? 'evento');

        return 'FINANCEIRO - '.mb_strtoupper(trim($name)).'.xlsx';
    }

    private function buildSummary(Spreadsheet $book, FinanceSheet $sheet): void
    {
        $ws = $book->createSheet();
        $ws->setTitle('RESUMO GERAL');

        $data = $this->summary->summary($sheet);
        $settlements = $this->summary->settlements($sheet, $data['result']['actual']);

        $ws->setCellValue('B2', 'EVENTO:')->setCellValue('C2', $sheet->event?->name);
        $ws->setCellValue('B3', 'RESUMO GERAL PREVISTO');
        $ws->setCellValue('E3', 'RESUMO GERAL REALIZADO');

        $ws->setCellValue('B4', 'RECEITA:')->setCellValue('C4', $data['revenue']['estimated']);
        $ws->setCellValue('B5', 'CUSTO:')->setCellValue('C5', $data['cost']['current_estimate']);
        $ws->setCellValue('B6', 'RESULTADO:')->setCellValue('C6', $data['result']['estimated']);

        $ws->setCellValue('E4', 'RECEITA:')->setCellValue('F4', $data['revenue']['actual']);
        $ws->setCellValue('E5', 'CUSTO:')->setCellValue('F5', $data['cost']['actual']);
        $ws->setCellValue('E6', 'RESULTADO:')->setCellValue('F6', $data['result']['actual']);

        $ws->setCellValue('B10', 'CUSTO POR ITEM');
        $ws->setCellValue('C10', 'PREVISTO');
        $ws->setCellValue('D10', 'REALIZADO');
        $row = 11;
        foreach ($this->summary->byCategory($sheet) as $line) {
            $ws->setCellValue("B{$row}", $line['label']);
            $ws->setCellValue("C{$row}", $line['estimated']);
            $ws->setCellValue("D{$row}", $line['actual']);
            $row++;
        }

        $ws->setCellValue('F10', 'ANDAMENTO');
        $ws->setCellValue('F11', 'PAGO:')->setCellValue('G11', $data['progress']['paid']);
        $ws->setCellValue('F12', 'FALTA PAGAR:')->setCellValue('G12', $data['progress']['pending']);

        $ws->setCellValue('F15', 'ACERTO SÓCIOS');
        $ws->setCellValue('F16', 'REPASSE SÓCIOS')->setCellValue('G16', 'PORCENTAGEM')->setCellValue('H16', 'TOTAL');
        $row = 17;
        foreach ($settlements as $s) {
            $ws->setCellValue("F{$row}", $s['partner_name']);
            $ws->setCellValue("G{$row}", $s['percentage'] / 100);
            $ws->setCellValue("H{$row}", $s['amount']);
            $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode('0.00%');
            $row++;
        }

        foreach (['B3', 'E3', 'B10', 'F10', 'F15'] as $cell) {
            $this->headerStyle($ws, $cell);
        }
        $this->money($ws, ['C4:C6', 'F4:F6', 'C11:D40', 'G11:G12', 'H17:H40']);
        $ws->getColumnDimension('B')->setWidth(30);
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $ws->getColumnDimension($col)->setWidth(18);
        }
    }

    private function buildRevenues(Spreadsheet $book, FinanceSheet $sheet): void
    {
        $ws = $book->createSheet();
        $ws->setTitle('RECEITAS');

        $ws->setCellValue('A1', 'RECEITAS');
        $headers = ['RECEITA', 'VALOR PREVISTO', 'VALOR REALIZADO', 'RECEBIDO', 'FALTA RECEBER', 'RECEBIDO POR', 'OBS'];
        foreach ($headers as $i => $header) {
            $ws->setCellValue([$i + 1, 3], $header);
            $this->headerStyle($ws, [$i + 1, 3]);
        }

        $row = 4;
        foreach ($sheet->revenues()->with('source:id,name')->orderBy('position')->orderBy('id')->get() as $revenue) {
            // A descrição do patrocínio entra junto do rótulo — é o "de quem veio" que o cliente pediu.
            $label = $revenue->category->spreadsheetLabel();
            if ($revenue->description) {
                $label .= ' | '.$revenue->description;
            }

            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("B{$row}", (float) $revenue->estimated_value);
            $ws->setCellValue("C{$row}", (float) $revenue->actual_value);
            $ws->setCellValue("D{$row}", (float) $revenue->received_value);
            $ws->setCellValue("E{$row}", (float) $revenue->pending_value);
            $ws->setCellValue("F{$row}", $revenue->source?->name);
            $ws->setCellValue("G{$row}", $revenue->notes);
            $row++;
        }

        $ws->setCellValue("A{$row}", 'TOTAL GERAL:');
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $ws->setCellValue("{$col}{$row}", $this->sumOrZero($col, 4, $row));
        }
        $this->headerStyle($ws, "A{$row}");
        $this->money($ws, ["B4:E{$row}"]);
        $ws->getColumnDimension('A')->setWidth(38);
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $ws->getColumnDimension($col)->setWidth(18);
        }
    }

    private function buildCosts(Spreadsheet $book, FinanceSheet $sheet): void
    {
        $ws = $book->createSheet();
        $ws->setTitle('CUSTOS');

        $sources = FinancePaymentSource::ordered()->get();
        $kinds = $this->controlKinds($sheet);

        $ws->setCellValue('A1', 'EVENTO:')->setCellValue('B1', $sheet->event?->name);
        $ws->setCellValue('A2', 'DATA:')->setCellValue('B2', $sheet->event?->start_date?->format('d/m/Y'));
        $ws->setCellValue('A3', 'LOCAL:')->setCellValue('B3', $sheet->event?->location);

        // Cabeçalho na mesma ordem do modelo; os grupos de pagamento entram como colunas dinâmicas
        // (na planilha eram fixos: CAIXA EVENTO, SÓCIO 1, SÓCIO 2, TICKETEIRA, BAR).
        $headers = array_merge(
            ['ITEM', 'DESCRIÇÃO', 'STATUS', 'ART', 'EMPRESA', 'AUTORIZADO POR', 'DIÁRIAS', 'QUANT.',
                'VALOR UNIT.', 'TOTAL PREVISTO', 'VALOR UNIT. 2', 'TOTAL PREVISTO 2',
                'VALOR UNIT. REALIZADO', 'TOTAL REALIZADO'],
            $sources->pluck('name')->map(fn ($n) => mb_strtoupper($n))->all(),
            ['PAGO', 'FALTA PAGAR'],
            array_map(fn (FinanceDocumentKind $k) => mb_strtoupper($k->label()), $kinds),
        );

        foreach ($headers as $i => $header) {
            $ws->setCellValue([$i + 1, 7], $header);
            $this->headerStyle($ws, [$i + 1, 7]);
        }

        // Onde começa o bloco CONTROLE: 14 colunas fixas + uma por grupo de pagamento + PAGO e
        // FALTA PAGAR. É nele que os links dos arquivos são aplicados, linha a linha.
        $controlFirstColumn = 14 + $sources->count() + 2 + 1;

        $row = 8;
        $items = $sheet->costItems()
            // `documents.attachment`: o nome exibido do documento que veio do card mora no anexo —
            // sem isso é um lazy load por linha (e o guard de N+1 derruba a exportação fora de produção).
            ->with([
                'categoria:id,nome', 'fornecedor:id,name', 'authorizer:id,name',
                'documents.attachment:id,original_name', 'payments',
            ])
            ->orderBy('position')->orderBy('id')->get();

        foreach ($items as $item) {
            $paid = (float) $item->payments->sum('amount');
            $values = array_merge([
                $item->categoria?->nome,
                $item->description,
                $item->status->spreadsheetLabel(),
                $item->art_status->spreadsheetLabel(),
                $item->supplierLabel(),
                $item->authorizerLabel(),
                (float) $item->daily_count,
                (float) $item->quantity,
                (float) $item->unit_estimated_1,
                (float) $item->total_estimated_1,
                $item->unit_estimated_2 === null ? null : (float) $item->unit_estimated_2,
                (float) $item->total_estimated_2,
                $item->unit_actual === null ? null : (float) $item->unit_actual,
                (float) $item->total_actual,
            ],
                $sources->map(fn ($s) => (float) $item->payments->where('finance_payment_source_id', $s->id)->sum('amount'))->all(),
                [$paid, (float) $item->total_actual - $paid],
                array_column($documentCells = $this->documentCells($item, $kinds), 'text'),
            );

            foreach ($values as $i => $value) {
                $ws->setCellValue([$i + 1, $row], $value);
            }

            // O arquivo em si continua fora do .xlsx (a planilha circula por e-mail); o que vai
            // junto é o link para abri-lo no sistema.
            foreach ($documentCells as $offset => $cell) {
                if ($cell['url'] === null) {
                    continue;
                }

                $coordinate = $ws->getCell([$controlFirstColumn + $offset, $row])->getCoordinate();
                $ws->getCell($coordinate)->getHyperlink()->setUrl($cell['url']);
                $ws->getStyle($coordinate)->getFont()->setUnderline(true)->getColor()->setARGB('FF1D4ED8');
            }

            $row++;
        }

        $ws->setCellValue("B{$row}", 'TOTAL GERAL:');
        $this->headerStyle($ws, "B{$row}");
        foreach (range(9, 14 + $sources->count() + 2) as $colIndex) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $ws->setCellValue("{$col}{$row}", $this->sumOrZero($col, 8, $row));
        }

        $moneyStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9);
        $moneyEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(14 + $sources->count() + 2);
        $this->money($ws, ["{$moneyStart}8:{$moneyEnd}{$row}"]);

        $ws->getColumnDimension('A')->setWidth(24);
        $ws->getColumnDimension('B')->setWidth(40);
        $ws->getColumnDimension('C')->setWidth(24);
        $ws->getColumnDimension('E')->setWidth(28);

        // As colunas de CONTROLE agora carregam o nome do arquivo, não mais um "X".
        foreach (range(0, count($kinds) - 1) as $offset) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($controlFirstColumn + $offset);
            $ws->getColumnDimension($column)->setWidth(30);
        }

        $ws->freezePane('C8');
    }

    /**
     * Quais tipos viram coluna no bloco CONTROLE.
     *
     * Os seis do arquivo modelo (V-AA) vêm sempre e nessa ordem: o import lê essas colunas por
     * POSIÇÃO FIXA, então mexer nelas quebraria a volta da planilha. Os demais tipos — Recibo,
     * Geral, Minuta — entram depois e só quando a planilha tem algum arquivo deles, que é a mesma
     * regra da coluna CONTROLE na tela: um tipo sem arquivo não é pendência, é só ruído.
     *
     * @return array<FinanceDocumentKind>
     */
    private function controlKinds(FinanceSheet $sheet): array
    {
        $used = FinanceDocument::query()
            ->whereIn('finance_cost_item_id', $sheet->costItems()->select('id'))
            ->pluck('kind')
            ->map(fn ($kind) => $kind instanceof FinanceDocumentKind ? $kind->value : (string) $kind)
            ->all();

        $extras = array_filter(
            FinanceDocumentKind::cases(),
            fn (FinanceDocumentKind $kind) => ! in_array($kind, FinanceDocumentKind::proofKinds(), true)
                && in_array($kind->value, $used, true),
        );

        return array_merge(FinanceDocumentKind::proofKinds(), array_values($extras));
    }

    /**
     * Uma célula por tipo de documento do bloco CONTROLE.
     *
     * A coluna era um "X" opaco: dizia que o comprovante existe, mas quem recebe a planilha
     * (contabilidade, sócios) tinha de entrar no sistema e procurar o arquivo. Agora ela mostra o
     * nome do arquivo e leva até ele — o link exige login, então a planilha não vira um caminho
     * aberto para documento de despesa.
     *
     * Com mais de um arquivo do mesmo tipo (parcelas de um pagamento, por exemplo) a célula linka o
     * primeiro e conta o resto: uma célula só comporta um hyperlink, e a lista completa continua na
     * aba Custos do sistema.
     *
     * @param  array<FinanceDocumentKind>  $kinds
     * @return array<int,array{text:string,url:?string}>
     */
    private function documentCells(FinanceCostItem $item, array $kinds): array
    {
        return array_map(function (FinanceDocumentKind $kind) use ($item) {
            $documents = $item->documents->where('kind', $kind);
            $first = $documents->first();

            if (! $first) {
                return ['text' => '', 'url' => null];
            }

            $others = $documents->count() - 1;

            return [
                'text' => Str::limit($first->displayName(), 40).($others > 0 ? " (+{$others})" : ''),
                'url' => route('finance.documents.show', $first),
            ];
        }, $kinds);
    }

    /**
     * `=SUM()` da coluna, da primeira linha de dados até a última — ou 0 quando não há nenhuma.
     *
     * Sem essa guarda, a planilha sem linhas gera `=SUM(B4:B3)`: um intervalo invertido que o Excel
     * normaliza para `B3:B4`, o que inclui a própria célula do total e faz o arquivo abrir com o
     * aviso de referência circular. Acontecia em qualquer evento ainda sem receitas lançadas.
     *
     * @param  int  $totalRow  linha onde o TOTAL GERAL será escrito (logo após a última linha)
     */
    private function sumOrZero(string $column, int $firstRow, int $totalRow): string|float
    {
        $lastRow = $totalRow - 1;

        return $lastRow >= $firstRow ? "=SUM({$column}{$firstRow}:{$column}{$lastRow})" : 0.0;
    }

    private function headerStyle($ws, $cell): void
    {
        $style = $ws->getStyle(is_array($cell) ? $ws->getCell($cell)->getCoordinate() : $cell);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** @param  array<string>  $ranges */
    private function money($ws, array $ranges): void
    {
        foreach ($ranges as $range) {
            $ws->getStyle($range)->getNumberFormat()->setFormatCode('"R$" #,##0.00');
        }
    }
}
