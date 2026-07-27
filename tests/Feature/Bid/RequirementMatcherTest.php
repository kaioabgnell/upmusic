<?php

namespace Tests\Feature\Bid;

use App\Domain\Enums\BidCompanySize;
use App\Domain\Enums\BidMatchStatus;
use App\Domain\Enums\BidRequirementKind;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;
use App\Models\BidNoticeRequirement;
use App\Services\Bid\RequirementMatcher;
use Database\Seeders\BidCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cruzamento requisito × empresa (specs/21 §10.3). Fica em Feature porque o catálogo de tipos
 * (apelidos) vem do banco — é justamente ele que dá precisão ao matcher.
 */
class RequirementMatcherTest extends TestCase
{
    use RefreshDatabase;

    private RequirementMatcher $matcher;

    private BidCompany $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BidCatalogSeeder::class);

        $this->matcher = new RequirementMatcher;
        $this->company = BidCompany::create([
            'corporate_name' => 'EMPRESA TESTE LTDA',
            'cnpj' => '19131243000197',
            'size' => BidCompanySize::Epp->value,
            'capital_social' => 150000,
            'net_worth' => 90000,
            'cnaes' => [['code' => '8011101', 'description' => 'Vigilância', 'primary' => true]],
        ]);
        $this->company->setRelation('businessLines', collect());
    }

    private function type(string $slug): BidDocumentType
    {
        return BidDocumentType::where('slug', $slug)->firstOrFail();
    }

    private function document(array $attributes = []): BidDocument
    {
        return BidDocument::create(array_merge([
            'bid_company_id' => $this->company->id,
            'bid_document_category_id' => BidDocumentCategory::where('slug', 'fiscal')->value('id'),
            'name' => 'Documento',
            'file_path' => 'licitacoes/1/x.pdf',
            'original_name' => 'x.pdf',
            'mime_type' => 'application/pdf',
            'expires_at' => Carbon::today()->addDays(120),
        ], $attributes));
    }

    private function requirement(array $attributes = []): BidNoticeRequirement
    {
        return new BidNoticeRequirement(array_merge([
            'kind' => BidRequirementKind::Documento,
            'name' => 'Certidão Negativa de Débitos Federais',
            'mandatory' => true,
            'source_excerpt' => 'trecho do edital',
        ], $attributes));
    }

    private function match(BidNoticeRequirement $requirement, ?float $estimated = null)
    {
        $documents = BidDocument::query()->current()->with('type')->get();

        return $this->matcher->match($requirement, $this->company, $documents, $estimated);
    }

    public function test_tipo_canonico_casa_com_confianca_alta(): void
    {
        $type = $this->type('cnd_federal');
        $this->document(['bid_document_type_id' => $type->id, 'name' => 'CND Federal 2026']);

        $result = $this->match($this->requirement(['bid_document_type_id' => $type->id]));

        $this->assertSame(BidMatchStatus::Atendido, $result->status);
        $this->assertSame('alta', $result->confidence);
    }

    public function test_tipo_exigido_ausente_no_acervo_vira_ausente(): void
    {
        $this->document(['bid_document_type_id' => $this->type('cndt')->id, 'name' => 'CNDT']);

        $result = $this->match($this->requirement(['bid_document_type_id' => $this->type('cnd_federal')->id]));

        $this->assertSame(BidMatchStatus::Ausente, $result->status);
        $this->assertStringContainsString('Nenhum documento do tipo', $result->reason);
    }

    public function test_apelido_do_catalogo_casa_com_confianca_media(): void
    {
        // O acervo tem o nome completo; o edital pede pelo apelido "CND Federal".
        $this->document([
            'bid_document_type_id' => $this->type('cnd_federal')->id,
            'name' => 'Certidão Negativa de Débitos Relativos aos Créditos Tributários Federais',
        ]);

        $result = $this->match($this->requirement(['name' => 'CND Federal', 'bid_document_type_id' => null]));

        $this->assertSame(BidMatchStatus::Atendido, $result->status);
        $this->assertSame('media', $result->confidence);
    }

    public function test_fuzzy_casa_por_semelhanca_com_confianca_baixa(): void
    {
        $fiscal = BidDocumentCategory::where('slug', 'fiscal')->value('id');
        $this->document(['name' => 'Certidão de Regularidade Municipal de Campinas', 'bid_document_category_id' => $fiscal]);

        $result = $this->match($this->requirement([
            'name' => 'Certidão de Regularidade Municipal',
            'bid_document_category_id' => $fiscal,
            'bid_document_type_id' => null,
        ]));

        $this->assertSame(BidMatchStatus::Atendido, $result->status);
        $this->assertSame('baixa', $result->confidence);
        $this->assertStringContainsString('semelhança', $result->reason);
    }

    public function test_fuzzy_nao_casa_documento_de_outra_natureza(): void
    {
        $this->document(['name' => 'Alvará de funcionamento 2026']);

        $result = $this->match($this->requirement(['name' => 'Balanço patrimonial', 'bid_document_type_id' => null]));

        $this->assertSame(BidMatchStatus::Ausente, $result->status);
    }

    public function test_melhor_documento_do_tipo_e_o_de_maior_validade(): void
    {
        $type = $this->type('crf_fgts');
        $this->document(['bid_document_type_id' => $type->id, 'name' => 'CRF antigo', 'expires_at' => Carbon::today()->subDay()]);
        $this->document(['bid_document_type_id' => $type->id, 'name' => 'CRF novo', 'expires_at' => Carbon::today()->addDays(200)]);

        $result = $this->match($this->requirement(['bid_document_type_id' => $type->id]));

        $this->assertSame(BidMatchStatus::Atendido, $result->status);
        $this->assertStringContainsString('CRF novo', $result->reason);
    }

    public function test_documento_vencido_reprova_o_requisito(): void
    {
        $type = $this->type('cnd_estadual');
        $this->document(['bid_document_type_id' => $type->id, 'name' => 'CND Estadual', 'expires_at' => Carbon::today()->subDays(3)]);

        $result = $this->match($this->requirement(['bid_document_type_id' => $type->id]));

        $this->assertSame(BidMatchStatus::Vencido, $result->status);
        $this->assertStringContainsString('Vencido há 3 dias', $result->reason);
    }

    public function test_cnae_compativel_e_incompativel(): void
    {
        $ok = $this->match($this->requirement([
            'kind' => BidRequirementKind::Cnae,
            'name' => 'CNAE compatível',
            'expected' => ['cnae' => '80111', 'cnae_label' => '8011-1/01'],
        ]));
        $this->assertSame(BidMatchStatus::Atendido, $ok->status);

        $nok = $this->match($this->requirement([
            'kind' => BidRequirementKind::Cnae,
            'name' => 'CNAE compatível',
            'expected' => ['cnae' => '41200', 'cnae_label' => '4120-4/00'],
        ]));
        $this->assertSame(BidMatchStatus::Ausente, $nok->status);
    }

    public function test_porte_exclusivo_me_epp(): void
    {
        $ok = $this->match($this->requirement([
            'kind' => BidRequirementKind::Porte,
            'name' => 'Item exclusivo ME/EPP',
            'expected' => ['size' => ['me', 'epp']],
        ]));
        $this->assertSame(BidMatchStatus::Atendido, $ok->status);

        $nok = $this->match($this->requirement([
            'kind' => BidRequirementKind::Porte,
            'name' => 'Item exclusivo ME',
            'expected' => ['size' => ['me']],
        ]));
        $this->assertSame(BidMatchStatus::Ausente, $nok->status);
        $this->assertStringContainsString('a empresa é EPP', $nok->reason);
    }

    public function test_capital_social_por_percentual_do_valor_estimado(): void
    {
        $requirement = $this->requirement([
            'kind' => BidRequirementKind::CapitalSocial,
            'name' => 'Capital social mínimo',
            'expected' => ['percent_of_estimate' => 10],
        ]);

        // 10% de R$ 1.240.000 = R$ 124.000 <= capital de R$ 150.000.
        $ok = $this->match($requirement, 1240000.0);
        $this->assertSame(BidMatchStatus::Atendido, $ok->status);
        $this->assertStringContainsString('R$ 124.000,00', $ok->reason);

        // 10% de R$ 2.000.000 = R$ 200.000 > capital.
        $nok = $this->match($requirement, 2000000.0);
        $this->assertSame(BidMatchStatus::Ausente, $nok->status);
    }

    public function test_valor_nao_cadastrado_na_empresa_vira_conferir(): void
    {
        $this->company->update(['net_worth' => null]);

        $result = $this->match($this->requirement([
            'kind' => BidRequirementKind::PatrimonioLiquido,
            'name' => 'Patrimônio líquido mínimo',
            'expected' => ['numeric_min' => 50000],
        ]));

        $this->assertSame(BidMatchStatus::Conferir, $result->status);
        $this->assertStringContainsString('não cadastrado', $result->reason);
    }

    public function test_atestado_existente_exige_conferencia_humana(): void
    {
        $this->document([
            'bid_document_type_id' => $this->type('atestado_capacidade_tecnica')->id,
            'name' => 'Atestado de capacidade técnica - portaria',
            'no_expiry' => true,
            'expires_at' => null,
        ]);

        $result = $this->match($this->requirement([
            'kind' => BidRequirementKind::AtestadoTecnico,
            'name' => 'Atestado de capacidade técnica',
        ]));

        // Existir no acervo não prova que o teor atende ao exigido.
        $this->assertSame(BidMatchStatus::Conferir, $result->status);
    }

    public function test_atestado_inexistente_e_ausente(): void
    {
        $result = $this->match($this->requirement([
            'kind' => BidRequirementKind::AtestadoTecnico,
            'name' => 'Atestado de capacidade técnica',
        ]));

        $this->assertSame(BidMatchStatus::Ausente, $result->status);
    }

    public function test_requisitos_nao_automatizaveis_viram_conferir(): void
    {
        foreach ([
            BidRequirementKind::IndiceContabil,
            BidRequirementKind::VisitaTecnica,
            BidRequirementKind::GarantiaProposta,
            BidRequirementKind::Outro,
        ] as $kind) {
            $result = $this->match($this->requirement(['kind' => $kind, 'name' => $kind->label()]));

            $this->assertSame(BidMatchStatus::Conferir, $result->status, "kind {$kind->value}");
        }
    }

    public function test_requisito_ignorado_vira_nao_aplicavel(): void
    {
        $result = $this->match($this->requirement([
            'ignored' => true,
            'ignored_reason' => 'Não se aplica ao objeto',
        ]));

        $this->assertSame(BidMatchStatus::NaoAplicavel, $result->status);
        $this->assertSame('Não se aplica ao objeto', $result->reason);
    }

    public function test_documento_substituido_nao_conta_para_habilitacao(): void
    {
        $type = $this->type('cnd_municipal');
        $this->document([
            'bid_document_type_id' => $type->id,
            'name' => 'CND Municipal antiga',
            'superseded_at' => now(),
        ]);

        $result = $this->match($this->requirement(['bid_document_type_id' => $type->id]));

        $this->assertSame(BidMatchStatus::Ausente, $result->status);
    }
}
