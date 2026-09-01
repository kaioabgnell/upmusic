<?php

namespace App\Actions\Finance;

use App\Domain\Enums\FinanceArtStatus;
use App\Domain\Enums\FinanceCostStatus;
use App\Domain\Enums\FinancePaymentSourceKind;
use App\Domain\Enums\FinanceRevenueCategory;
use App\Models\FinancePaymentSource;
use App\Models\FinanceSheet;
use App\Models\FornecedorCategoria;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Migração da planilha existente (specs/23 §12): lê um `FINANCEIRO - MODELO.xlsx` já preenchido e
 * converte em linhas do sistema. É a ferramenta de adoção — há eventos em curso hoje no arquivo.
 *
 * O bloco CONTROLE (colunas V-AA) do arquivo é só marcação textual, NÃO arquivo: vira observação
 * na linha, nunca `finance_documents`. Documento sem arquivo seria uma prestação de contas falsa.
 */
class ImportFinanceSpreadsheet
{
    /** Posição das colunas fixas da aba CUSTOS no arquivo modelo (1-indexed). */
    private const COST_COLUMNS = [
        'item' => 1, 'description' => 2, 'status' => 3, 'art' => 4, 'supplier' => 5,
        'authorized' => 6, 'daily' => 7, 'quantity' => 8,
        'unit_1' => 9, 'unit_2' => 11, 'unit_actual' => 13,
    ];

    private const PAYMENT_COLUMNS = [15, 16, 17, 18, 19];   // O..S

    private const CONTROL_COLUMNS = [22, 23, 24, 25, 26, 27];   // V..AA

    /**
     * Lê o arquivo e devolve as linhas para a pré-visualização — nada é gravado aqui.
     *
     * @return array{costs:array<int,array>,revenues:array<int,array>,warnings:array<int,string>}
     */
    public function preview(UploadedFile $file): array
    {
        $book = IOFactory::createReaderForFile($file->getRealPath());
        $book->setReadDataOnly(true);
        $spreadsheet = $book->load($file->getRealPath());

        $warnings = [];
        $categorias = $this->categoriaIndex();

        $costs = $this->readCosts($spreadsheet, $categorias, $warnings);
        $revenues = $this->readRevenues($spreadsheet, $warnings);

        if (! $costs && ! $revenues) {
            $warnings[] = 'Nenhuma linha aproveitável encontrada nas abas CUSTOS e RECEITAS.';
        }

        return ['costs' => $costs, 'revenues' => $revenues, 'warnings' => $warnings];
    }

    /**
     * Grava as linhas confirmadas na pré-visualização.
     *
     * @return array{costs:int,revenues:int,payments:int}
     */
    public function import(FinanceSheet $sheet, array $costs, array $revenues, ?User $actor = null): array
    {
        return DB::transaction(function () use ($sheet, $costs, $revenues, $actor) {
            $counts = ['costs' => 0, 'revenues' => 0, 'payments' => 0];
            $position = (int) $sheet->costItems()->max('position');
            $sources = $this->sourceIndex();

            foreach ($costs as $row) {
                if (empty($row['description'])) {
                    continue;
                }

                $item = $sheet->costItems()->create([
                    'fornecedor_categoria_id' => $row['fornecedor_categoria_id'] ?? null,
                    'description' => $row['description'],
                    'status' => $row['status'] ?? FinanceCostStatus::Orcamento->value,
                    // Status veio do arquivo: não deixar a derivação automática sobrescrever o que
                    // a operação já havia decidido lá.
                    'status_auto' => false,
                    'art_status' => $row['art_status'] ?? FinanceArtStatus::NaoTem->value,
                    'supplier_name' => $row['supplier_name'] ?? null,
                    'authorized_by_name' => $row['authorized_by_name'] ?? null,
                    'daily_count' => $row['daily_count'] ?? 1,
                    'quantity' => $row['quantity'] ?? 1,
                    'unit_estimated_1' => $row['unit_estimated_1'] ?? 0,
                    'unit_estimated_2' => $row['unit_estimated_2'] ?? null,
                    'unit_actual' => $row['unit_actual'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'position' => ++$position,
                ]);
                $counts['costs']++;

                foreach ($row['payments'] ?? [] as $sourceName => $amount) {
                    if ((float) $amount <= 0) {
                        continue;
                    }

                    $item->payments()->create([
                        'finance_payment_source_id' => $this->resolveSource($sourceName, $sources),
                        'amount' => $amount,
                        'created_by' => $actor?->id,
                    ]);
                    $counts['payments']++;
                }
            }

            $revenuePosition = (int) $sheet->revenues()->max('position');

            foreach ($revenues as $row) {
                if (empty($row['category'])) {
                    continue;
                }

                $sheet->revenues()->create([
                    'category' => $row['category'],
                    'description' => $row['description'] ?? null,
                    'estimated_value' => $row['estimated_value'] ?? 0,
                    'actual_value' => $row['actual_value'] ?? 0,
                    'received_value' => $row['received_value'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                    'position' => ++$revenuePosition,
                ]);
                $counts['revenues']++;
            }

            return $counts;
        });
    }

    // ------------------------------------------------------------------

    private function readCosts($spreadsheet, array $categorias, array &$warnings): array
    {
        $ws = $spreadsheet->getSheetByName('CUSTOS');

        if (! $ws) {
            $warnings[] = 'A aba CUSTOS não foi encontrada no arquivo.';

            return [];
        }

        // Rótulos dos grupos de pagamento saem do cabeçalho (linha 7) do próprio arquivo.
        $sourceNames = [];
        foreach (self::PAYMENT_COLUMNS as $col) {
            $sourceNames[$col] = trim((string) $ws->getCell([$col, 7])->getValue());
        }

        $controlLabels = [];
        foreach (self::CONTROL_COLUMNS as $col) {
            $controlLabels[$col] = trim((string) $ws->getCell([$col, 7])->getValue());
        }

        $rows = [];

        for ($row = 8; $row <= $ws->getHighestDataRow(); $row++) {
            $description = trim((string) $ws->getCell([self::COST_COLUMNS['description'], $row])->getValue());
            $itemName = trim((string) $ws->getCell([self::COST_COLUMNS['item'], $row])->getValue());

            if ($description === '' && $itemName === '') {
                continue;
            }

            $unit1 = $this->number($ws->getCell([self::COST_COLUMNS['unit_1'], $row])->getValue());
            $unit2 = $this->number($ws->getCell([self::COST_COLUMNS['unit_2'], $row])->getValue());
            $actual = $this->number($ws->getCell([self::COST_COLUMNS['unit_actual'], $row])->getValue());

            // Linha só com o rótulo do catálogo (sem nenhum valor) é estrutura do modelo, não gasto.
            if ($unit1 === null && $unit2 === null && $actual === null) {
                continue;
            }

            $categoriaId = $categorias[$this->normalize($itemName)] ?? null;

            if ($itemName !== '' && ! $categoriaId) {
                $warnings[] = "Categoria \"{$itemName}\" (linha {$row}) não existe no sistema — a linha entra sem categoria.";
            }

            $payments = [];
            foreach (self::PAYMENT_COLUMNS as $col) {
                $amount = $this->number($ws->getCell([$col, $row])->getValue());
                if ($amount !== null && $amount > 0 && $sourceNames[$col] !== '') {
                    $payments[$sourceNames[$col]] = $amount;
                }
            }

            $controls = [];
            foreach (self::CONTROL_COLUMNS as $col) {
                $value = trim((string) $ws->getCell([$col, $row])->getValue());
                if ($value !== '' && $controlLabels[$col] !== '') {
                    $controls[] = $controlLabels[$col];
                }
            }

            $rows[] = [
                'source_row' => $row,
                'item_name' => $itemName,
                'fornecedor_categoria_id' => $categoriaId,
                'description' => $description ?: $itemName,
                'status' => $this->mapStatus((string) $ws->getCell([self::COST_COLUMNS['status'], $row])->getValue()),
                'art_status' => $this->mapArt((string) $ws->getCell([self::COST_COLUMNS['art'], $row])->getValue()),
                'supplier_name' => trim((string) $ws->getCell([self::COST_COLUMNS['supplier'], $row])->getValue()) ?: null,
                'authorized_by_name' => trim((string) $ws->getCell([self::COST_COLUMNS['authorized'], $row])->getValue()) ?: null,
                'daily_count' => $this->number($ws->getCell([self::COST_COLUMNS['daily'], $row])->getValue()) ?: 1,
                'quantity' => $this->number($ws->getCell([self::COST_COLUMNS['quantity'], $row])->getValue()) ?: 1,
                'unit_estimated_1' => $unit1 ?? 0,
                'unit_estimated_2' => $unit2,
                'unit_actual' => $actual,
                'payments' => $payments,
                // O que o arquivo marcava como controle vira registro textual — o arquivo não traz
                // os documentos, e inventar `finance_documents` sem arquivo seria prova falsa.
                'notes' => $controls ? 'No arquivo original constava: '.implode(', ', $controls).'.' : null,
            ];
        }

        return $rows;
    }

    private function readRevenues($spreadsheet, array &$warnings): array
    {
        $ws = $spreadsheet->getSheetByName('RECEITAS');

        if (! $ws) {
            $warnings[] = 'A aba RECEITAS não foi encontrada no arquivo.';

            return [];
        }

        $map = [];
        foreach (FinanceRevenueCategory::cases() as $case) {
            $map[$this->normalize($case->spreadsheetLabel())] = $case;
        }

        $rows = [];

        for ($row = 4; $row <= $ws->getHighestDataRow(); $row++) {
            $label = trim((string) $ws->getCell([1, $row])->getValue());

            if ($label === '' || str_starts_with(mb_strtoupper($label), 'TOTAL')) {
                continue;
            }

            // "PATROCÍNIO | Cliente X": o que vem depois da barra é a descrição do que entrou.
            [$categoryPart, $descriptionPart] = array_pad(array_map('trim', explode('|', $label, 2)), 2, null);
            $category = $map[$this->normalize($label)] ?? $map[$this->normalize((string) $categoryPart)] ?? null;

            if (! $category) {
                $warnings[] = "Receita \"{$label}\" (linha {$row}) não casou com nenhuma categoria — entra como \"Outros\".";
                $category = FinanceRevenueCategory::Outros;
                $descriptionPart = $label;
            }

            $estimated = $this->number($ws->getCell([2, $row])->getValue()) ?? 0;
            $actual = $this->number($ws->getCell([3, $row])->getValue()) ?? 0;
            $received = $this->number($ws->getCell([4, $row])->getValue()) ?? 0;

            if ($estimated == 0 && $actual == 0 && $received == 0) {
                continue;
            }

            $rows[] = [
                'source_row' => $row,
                'category' => $category->value,
                'category_label' => $category->label(),
                'description' => $descriptionPart ?: null,
                'estimated_value' => $estimated,
                'actual_value' => $actual,
                'received_value' => $received,
                'notes' => trim((string) $ws->getCell([7, $row])->getValue()) ?: null,
            ];
        }

        return $rows;
    }

    /** @return array<string,int> nome normalizado => id */
    private function categoriaIndex(): array
    {
        return FornecedorCategoria::pluck('id', 'nome')
            ->mapWithKeys(fn ($id, $nome) => [$this->normalize($nome) => $id])
            ->all();
    }

    /** @return array<string,int> */
    private function sourceIndex(): array
    {
        return FinancePaymentSource::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [$this->normalize($name) => $id])
            ->all();
    }

    /**
     * Grupo de pagamento do arquivo que não existe no catálogo é criado — o import declara isso
     * nos avisos da pré-visualização, e perder o "quem pagou" seria pior que um grupo a mais.
     *
     * @param  array<string,int>  $sources
     */
    private function resolveSource(string $name, array &$sources): int
    {
        $key = $this->normalize($name);

        if (! isset($sources[$key])) {
            $sources[$key] = FinancePaymentSource::create([
                'name' => mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'),
                'kind' => FinancePaymentSourceKind::Outro->value,
                'active' => true,
                'position' => (int) FinancePaymentSource::max('position') + 1,
            ])->id;
        }

        return $sources[$key];
    }

    private function mapStatus(string $label): string
    {
        $key = $this->normalize($label);

        foreach (FinanceCostStatus::cases() as $case) {
            if ($this->normalize($case->spreadsheetLabel()) === $key) {
                return $case->value;
            }
        }

        return FinanceCostStatus::Orcamento->value;
    }

    private function mapArt(string $label): string
    {
        $key = $this->normalize($label);

        foreach (FinanceArtStatus::cases() as $case) {
            if ($this->normalize($case->spreadsheetLabel()) === $key) {
                return $case->value;
            }
        }

        return FinanceArtStatus::NaoTem->value;
    }

    private function number($value): ?float
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Célula com fórmula não calculada ou texto ("R$ 1.234,56") — cai no parser BR.
        return \App\Support\Br::money((string) $value);
    }

    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $ascii)));
    }
}
