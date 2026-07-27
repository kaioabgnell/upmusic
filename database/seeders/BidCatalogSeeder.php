<?php

namespace Database\Seeders;

use App\Models\BidBusinessLine;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;
use Illuminate\Database\Seeder;

/**
 * Catálogo do módulo de Licitações (ver specs/21 §6.3, §6.4 e §6.2).
 *
 * Os `slug` de categoria e de tipo são o contrato com a IA (entram como enum no responseSchema),
 * então este seeder é idempotente e pode ser reexecutado sem duplicar nada.
 */
class BidCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $this->seedTypes($categories);
        $this->seedBusinessLines();
    }

    /** @return array<string,int> slug => id */
    private function seedCategories(): array
    {
        $rows = [
            ['slug' => 'fiscal', 'name' => 'Fiscal', 'icon' => 'fa-file-invoice-dollar', 'color' => '#1d4ed8'],
            ['slug' => 'trabalhista', 'name' => 'Trabalhista', 'icon' => 'fa-helmet-safety', 'color' => '#7c3aed'],
            ['slug' => 'juridica', 'name' => 'Jurídica', 'icon' => 'fa-scale-balanced', 'color' => '#0f766e'],
            ['slug' => 'tecnica', 'name' => 'Técnica', 'icon' => 'fa-screwdriver-wrench', 'color' => '#b45309'],
            ['slug' => 'financeira', 'name' => 'Financeira', 'icon' => 'fa-chart-pie', 'color' => '#be123c'],
            ['slug' => 'outros', 'name' => 'Outros', 'icon' => 'fa-folder-open', 'color' => '#5a5a5c'],
        ];

        $ids = [];

        foreach ($rows as $i => $row) {
            $category = BidDocumentCategory::updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['sort_order' => $i + 1, 'system' => true, 'active' => true]
            );
            $ids[$row['slug']] = $category->id;
        }

        return $ids;
    }

    /** @param  array<string,int>  $categories */
    private function seedTypes(array $categories): void
    {
        // [slug, nome, categoria, apelidos, órgão, validade padrão, exige código, essencial]
        $types = [
            // Fiscal
            ['cnd_federal', 'Certidão Negativa de Débitos Relativos a Créditos Tributários Federais e à Dívida Ativa da União', 'fiscal',
                ['cnd federal', 'certidao conjunta pgfn', 'certidao negativa federal', 'certidao conjunta receita federal', 'cnd receita federal', 'certidao negativa de debitos federais'],
                'Receita Federal / PGFN', 180, true, true],
            ['cnd_estadual', 'Certidão Negativa de Débitos Estaduais', 'fiscal',
                ['cnd estadual', 'certidao negativa estadual', 'certidao de regularidade fiscal estadual', 'certidao negativa de tributos estaduais'],
                'Secretaria da Fazenda Estadual', 180, true, true],
            ['cnd_municipal', 'Certidão Negativa de Débitos Municipais', 'fiscal',
                ['cnd municipal', 'certidao negativa municipal', 'certidao de tributos municipais', 'certidao negativa mobiliaria'],
                'Prefeitura Municipal', 180, true, true],
            ['crf_fgts', 'Certificado de Regularidade do FGTS (CRF)', 'fiscal',
                ['crf', 'fgts', 'certificado de regularidade do fgts', 'crf fgts', 'regularidade fgts', 'certidao fgts'],
                'Caixa Econômica Federal', 30, true, true],

            // Trabalhista
            ['cndt', 'Certidão Negativa de Débitos Trabalhistas (CNDT)', 'trabalhista',
                ['cndt', 'certidao negativa de debitos trabalhistas', 'certidao trabalhista', 'debitos trabalhistas'],
                'Tribunal Superior do Trabalho', 180, true, true],

            // Jurídica
            ['contrato_social', 'Contrato social consolidado e alterações', 'juridica',
                ['contrato social', 'contrato social consolidado', 'estatuto social', 'ultima alteracao contratual'],
                'Junta Comercial', null, false, true],
            ['ato_constitutivo', 'Ato constitutivo / registro comercial', 'juridica',
                ['ato constitutivo', 'registro comercial', 'requerimento de empresario', 'declaracao de firma individual'],
                'Junta Comercial', null, false, false],
            ['comprovante_cnpj', 'Comprovante de Inscrição e Situação Cadastral (CNPJ)', 'juridica',
                ['cartao cnpj', 'comprovante de inscricao e situacao cadastral', 'inscricao no cnpj', 'cnpj'],
                'Receita Federal', null, false, true],
            ['procuracao', 'Procuração / instrumento de mandato', 'juridica',
                ['procuracao', 'instrumento de mandato', 'instrumento procuratorio'],
                null, null, false, false],
            ['certidao_simplificada_junta', 'Certidão simplificada da Junta Comercial', 'juridica',
                ['certidao simplificada', 'certidao simplificada junta comercial', 'certidao da junta comercial'],
                'Junta Comercial', 90, false, false],
            ['alvara_funcionamento', 'Alvará de funcionamento', 'juridica',
                ['alvara', 'alvara de funcionamento', 'licenca de funcionamento'],
                'Prefeitura Municipal', 365, false, false],

            // Técnica
            ['atestado_capacidade_tecnica', 'Atestado de Capacidade Técnica', 'tecnica',
                ['atestado de capacidade tecnica', 'atestado tecnico', 'atestado de capacidade', 'comprovacao de aptidao tecnica'],
                null, null, false, false],
            ['registro_crea_cau', 'Registro/certidão no CREA ou CAU', 'tecnica',
                ['crea', 'cau', 'registro no crea', 'certidao de registro e quitacao crea', 'registro profissional'],
                'CREA / CAU', 365, false, false],
            ['cat_crea', 'Certidão de Acervo Técnico (CAT)', 'tecnica',
                ['cat', 'certidao de acervo tecnico', 'acervo tecnico'],
                'CREA', null, false, false],
            ['licenca_ambiental', 'Licença ambiental', 'tecnica',
                ['licenca ambiental', 'licenca de operacao ambiental'],
                null, 365, false, false],

            // Financeira
            ['balanco_patrimonial', 'Balanço patrimonial e demonstrações contábeis', 'financeira',
                ['balanco patrimonial', 'demonstracoes contabeis', 'balanco', 'dre', 'demonstracao do resultado'],
                null, null, false, true],
            ['certidao_falencia_recuperacao', 'Certidão negativa de falência, concordata e recuperação judicial', 'financeira',
                ['certidao de falencia', 'falencia e concordata', 'recuperacao judicial', 'certidao civel', 'certidao negativa de falencia'],
                'Tribunal de Justiça', 90, true, true],
            ['comprovante_capital_social', 'Comprovante de capital social integralizado', 'financeira',
                ['capital social', 'comprovacao de capital social', 'capital social integralizado'],
                null, null, false, false],
            ['demonstracao_indices', 'Demonstração dos índices contábeis', 'financeira',
                ['indices contabeis', 'demonstracao de indices', 'liquidez corrente', 'grau de endividamento', 'liquidez geral'],
                null, null, false, false],

            // Outros
            ['sicaf', 'Consulta consolidada / SICAF', 'outros',
                ['sicaf', 'consulta consolidada', 'certificado de registro cadastral sicaf'],
                'Compras.gov.br', 30, false, false],
            ['crc', 'Certificado de Registro Cadastral (CRC)', 'outros',
                ['crc', 'certificado de registro cadastral', 'registro cadastral'],
                null, 365, false, false],
            ['declaracoes_diversas', 'Declarações exigidas em edital', 'outros',
                ['declaracao', 'declaracoes', 'declaracao de menor', 'declaracao de idoneidade', 'declaracao de elaboracao independente'],
                null, null, false, false],
        ];

        foreach ($types as $i => [$slug, $name, $categorySlug, $aliases, $issuer, $validity, $requiresCode, $essential]) {
            BidDocumentType::updateOrCreate(['slug' => $slug], [
                'bid_document_category_id' => $categories[$categorySlug],
                'name' => $name,
                'aliases' => $aliases,
                'issuer' => $issuer,
                'default_validity_days' => $validity,
                'requires_control_code' => $requiresCode,
                'essential' => $essential,
                'sort_order' => $i + 1,
                'active' => true,
            ]);
        }
    }

    private function seedBusinessLines(): void
    {
        $lines = [
            ['Eventos', ['evento', 'eventos', 'show', 'shows', 'palco', 'sonorizacao', 'iluminacao', 'festa', 'festival', 'estrutura para evento']],
            ['Portaria', ['portaria', 'porteiro', 'controle de acesso', 'recepcao', 'zeladoria']],
            ['Segurança', ['seguranca', 'vigilancia', 'vigilante', 'seguranca desarmada', 'seguranca patrimonial']],
            ['Limpeza e conservação', ['limpeza', 'conservacao', 'higienizacao', 'asseio', 'copeiragem']],
            ['Locação de equipamentos', ['locacao', 'aluguel de equipamentos', 'cessao de equipamentos', 'locacao de estruturas']],
            ['Serviços gerais', ['servicos gerais', 'apoio operacional', 'mao de obra', 'terceirizacao']],
            ['Produção audiovisual', ['audiovisual', 'filmagem', 'transmissao', 'gravacao', 'painel de led']],
        ];

        foreach ($lines as [$name, $keywords]) {
            BidBusinessLine::updateOrCreate(['name' => $name], [
                'keywords' => $keywords,
                'active' => true,
            ]);
        }
    }
}
