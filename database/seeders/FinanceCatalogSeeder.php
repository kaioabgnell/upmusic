<?php

namespace Database\Seeders;

use App\Domain\Enums\FinancePaymentSourceKind;
use App\Models\FinanceItemPreset;
use App\Models\FinancePaymentSource;
use App\Models\FornecedorCategoria;
use Illuminate\Database\Seeder;

/**
 * Catálogo do Financeiro do Evento (specs/23) — idempotente, pode rodar quantas vezes precisar.
 *
 *  1. Categorias de custo: acrescenta as que a planilha usa e ainda não existem em
 *     `fornecedor_categorias`. As existentes são REAPROVEITADAS como estão (nada é renomeado nem
 *     duplicado) para não quebrar `price_records` nem `fornecedores`.
 *  2. Grupos de pagamento: espelham as colunas O-S do arquivo modelo.
 *  3. Presets de descrição: as 168 linhas do modelo, lidas de
 *     `database/data/financeiro-itens-modelo.csv`.
 */
class FinanceCatalogSeeder extends Seeder
{
    /**
     * Categorias da lista suspensa da coluna ITEM (data validation A8:A183 do arquivo), no rótulo
     * que o sistema exibe. A chave é o nome como aparece no arquivo (caixa alta).
     */
    private const CATEGORIES = [
        'LICENÇAS E TAXAS' => 'Licenças e Taxas',
        'PROJETO' => 'Projeto',
        'LOGÍSTICA' => 'Logística',
        'DIVULGAÇÃO' => 'Divulgação',
        'MÍDIA' => 'Mídia',
        'ESTRUTURA GERAL' => 'Estrutura Geral',
        'CENOGRAFIA' => 'Cenografia',
        'RH' => 'RH',
        'SERVIÇOS' => 'Serviços',
        'CAMARIM' => 'Camarim',
        'BAR' => 'Bar',
        'OUTROS' => 'Outros',
        'ARTÍSTICO' => 'Artístico',
        'ESTRUTURA PALCO' => 'Estrutura Palco',
        'ESTRUTURA CAM EMPRESARIAL' => 'Estrutura Cam Empresarial',
        'RODEIO' => 'Rodeio',
        'ESTRUTURA CAMAROTE' => 'Estrutura Camarote',
        'PREFEITURA' => 'Prefeitura',
    ];

    private const PAYMENT_SOURCES = [
        ['Caixa do Evento', FinancePaymentSourceKind::Caixa],
        ['Sócio 1', FinancePaymentSourceKind::Socio],
        ['Sócio 2', FinancePaymentSourceKind::Socio],
        ['Ticketeira', FinancePaymentSourceKind::Ticketeira],
        ['Bar', FinancePaymentSourceKind::Bar],
    ];

    public function run(): void
    {
        $categorias = $this->seedCategorias();
        $this->seedPaymentSources();
        $this->seedItemPresets($categorias);
    }

    /**
     * @return array<string,int> nome normalizado => id
     */
    private function seedCategorias(): array
    {
        // Índice por nome normalizado (sem acento, caixa alta) para casar "Licenças e Taxas" com
        // "LICENÇAS E TAXAS" sem criar categoria duplicada.
        $existing = FornecedorCategoria::withTrashed()->get(['id', 'nome'])
            ->mapWithKeys(fn ($c) => [$this->normalize($c->nome) => $c->id])
            ->all();

        foreach (self::CATEGORIES as $sheetName => $displayName) {
            $key = $this->normalize($sheetName);

            if (! isset($existing[$key])) {
                $existing[$key] = FornecedorCategoria::create(['nome' => $displayName, 'active' => true])->id;
            }
        }

        return $existing;
    }

    private function seedPaymentSources(): void
    {
        foreach (self::PAYMENT_SOURCES as $i => [$name, $kind]) {
            FinancePaymentSource::firstOrCreate(
                ['name' => $name],
                ['kind' => $kind->value, 'active' => true, 'position' => $i],
            );
        }
    }

    /** @param  array<string,int>  $categorias */
    private function seedItemPresets(array $categorias): void
    {
        $path = database_path('data/financeiro-itens-modelo.csv');

        if (! is_readable($path)) {
            $this->command?->warn("Catálogo de itens não encontrado em {$path} — presets não semeados.");

            return;
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // cabeçalho: categoria,descricao

        $rows = [];
        $now = now();

        while (($cols = fgetcsv($handle)) !== false) {
            [$categoria, $descricao] = array_pad($cols, 2, null);
            $id = $categorias[$this->normalize((string) $categoria)] ?? null;

            if (! $id || ! $descricao) {
                continue;
            }

            $rows[] = [
                'fornecedor_categoria_id' => $id,
                'description' => trim($descricao),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        // upsert pelo unique (categoria, descrição): rodar de novo não duplica nem sobrescreve
        // uma descrição que o usuário tenha desativado.
        foreach (array_chunk($rows, 100) as $chunk) {
            FinanceItemPreset::upsert($chunk, ['fornecedor_categoria_id', 'description'], ['updated_at']);
        }
    }

    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $ascii)));
    }
}
