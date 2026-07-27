<?php

namespace Tests\Feature\Bid;

use App\Domain\Enums\UserRole;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;
use App\Models\User;
use Database\Seeders\BidCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cofre de documentos: upload, validação, versionamento e download (specs/21 §9.3, §10.2, §12). */
class BidDocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BidCompany $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BidCatalogSeeder::class);

        // `->fresh()`: sem isso o modelo não traz `avatar_path` e o layout quebra em modo estrito.
        $this->admin = User::factory()->create([
            'name' => 'Admin', 'email' => 'admin@teste.local',
            'role' => UserRole::Admin->value, 'active' => true,
        ])->fresh();

        $this->company = BidCompany::create([
            'corporate_name' => 'EMPRESA TESTE LTDA',
            'cnpj' => '19131243000197',
            'size' => 'epp',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'CND Federal',
            'bid_document_category_id' => BidDocumentCategory::where('slug', 'fiscal')->value('id'),
            'bid_document_type_id' => BidDocumentType::where('slug', 'cnd_federal')->value('id'),
            'expires_at' => Carbon::today()->addDays(120)->toDateString(),
            'control_code' => 'ABC-123',
            'no_expiry' => 0,
            'arquivo' => UploadedFile::fake()->create('cnd.pdf', 120, 'application/pdf'),
        ], $overrides);
    }

    /**
     * Regressão: o modal envia `ai_extracted` como STRING JSON serializada num input hidden
     * (specs/21 §9.4), não como campos aninhados. Sem decodificar antes da regra `array`, todo
     * upload feito após a leitura assistida falhava a validação em silêncio — o modal fechava, a
     * página recarregava e nada era salvo, sem nenhum erro visível para o usuário.
     */
    public function test_upload_apos_leitura_assistida_salva_mesmo_com_ai_extracted_serializado(): void
    {
        $suggestion = [
            'name' => 'Comprovante de Inscrição e de Situação Cadastral',
            'type_slug' => 'comprovante_cnpj',
            'category_slug' => 'juridica',
            'no_expiry' => true,
            'confidence' => 0.95,
            'warnings' => [],
        ];

        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), $this->payload([
                'name' => $suggestion['name'],
                'bid_document_category_id' => BidDocumentCategory::where('slug', 'juridica')->value('id'),
                'bid_document_type_id' => BidDocumentType::where('slug', 'comprovante_cnpj')->value('id'),
                'no_expiry' => 1,
                'expires_at' => null,
                'control_code' => null,
                'ai_extracted' => json_encode($suggestion),
                'ai_confidence' => 0.95,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bid.companies.show', $this->company));

        $document = BidDocument::firstOrFail();
        $this->assertSame($suggestion['name'], $document->name);
        // O array decodificado fica gravado — não a string serializada nem um double-encode.
        $this->assertSame($suggestion, $document->ai_extracted);
    }

    public function test_upload_grava_documento_fora_de_public_com_nome_sanitizado(): void
    {
        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), $this->payload([
                'arquivo' => UploadedFile::fake()->create('Certidão Negativa (2026).pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('bid.companies.show', $this->company));

        $document = BidDocument::firstOrFail();

        $this->assertStringStartsWith("licitacoes/{$this->company->id}/", $document->file_path);
        // Sem acentos, espaços ou parênteses — o Storage e o servidor web agradecem.
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._\-\/]+$/', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_anexo_e_obrigatorio(): void
    {
        $payload = $this->payload();
        unset($payload['arquivo']);

        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), $payload)
            ->assertSessionHasErrors('arquivo');

        $this->assertDatabaseCount('bid_documents', 0);
    }

    public function test_formato_de_arquivo_proibido_e_rejeitado(): void
    {
        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), $this->payload([
                'arquivo' => UploadedFile::fake()->create('malicioso.php', 10, 'application/x-httpd-php'),
            ]))
            ->assertSessionHasErrors('arquivo');

        $this->assertDatabaseCount('bid_documents', 0);
    }

    public function test_validade_obrigatoria_salvo_quando_marcado_sem_validade(): void
    {
        $payload = $this->payload();
        unset($payload['expires_at']);

        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), $payload)
            ->assertSessionHasErrors('expires_at');

        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), array_merge($payload, [
                'no_expiry' => 1,
                'name' => 'Contrato social',
                'bid_document_type_id' => BidDocumentType::where('slug', 'contrato_social')->value('id'),
                'bid_document_category_id' => BidDocumentCategory::where('slug', 'juridica')->value('id'),
                'arquivo' => UploadedFile::fake()->create('contrato.pdf', 50, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(BidDocument::firstOrFail()->no_expiry);
        $this->assertNull(BidDocument::firstOrFail()->expires_at);
    }

    public function test_codigo_de_controle_exigido_pelo_tipo(): void
    {
        $payload = $this->payload();
        unset($payload['control_code']);

        // `cnd_federal` está marcado no catálogo como exigindo código de autenticação.
        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->company), $payload)
            ->assertSessionHasErrors('control_code');
    }

    public function test_tipo_canonico_corrige_a_categoria_informada(): void
    {
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload([
            // Categoria errada de propósito: o tipo canônico é quem manda.
            'bid_document_category_id' => BidDocumentCategory::where('slug', 'outros')->value('id'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            BidDocumentCategory::where('slug', 'fiscal')->value('id'),
            BidDocument::firstOrFail()->bid_document_category_id
        );
    }

    public function test_renovacao_versiona_e_mantem_um_unico_documento_vigente(): void
    {
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload([
            'expires_at' => Carbon::today()->subDays(2)->toDateString(),
        ]));

        $original = BidDocument::firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('bid.documents.renew', $original), $this->payload([
                'expires_at' => Carbon::today()->addDays(180)->toDateString(),
                'arquivo' => UploadedFile::fake()->create('cnd-nova.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('bid.companies.show', $this->company->id));

        $original->refresh();
        $nova = BidDocument::query()->current()->firstOrFail();

        $this->assertNotNull($original->superseded_at, 'a versão antiga precisa sair do acervo vigente');
        $this->assertSame($original->id, $nova->supersedes_id);
        $this->assertCount(1, BidDocument::query()->current()->get());
        // O arquivo antigo continua acessível: é o histórico de conformidade.
        Storage::disk('local')->assertExists($original->file_path);
    }

    public function test_visitante_nao_baixa_arquivo_do_acervo(): void
    {
        $document = BidDocument::create([
            'bid_company_id' => $this->company->id,
            'bid_document_category_id' => BidDocumentCategory::where('slug', 'fiscal')->value('id'),
            'name' => 'CND Federal', 'file_path' => 'licitacoes/1/x.pdf',
            'original_name' => 'x.pdf', 'mime_type' => 'application/pdf',
            'expires_at' => Carbon::today()->addDays(30),
        ]);

        $this->get(route('bid.documents.file', $document))->assertRedirect(route('login'));
    }

    public function test_download_serve_pelo_laravel_para_admin(): void
    {
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload());
        $document = BidDocument::firstOrFail();

        $this->actingAs($this->admin)->get(route('bid.documents.file', $document))->assertOk();
        $this->actingAs($this->admin)
            ->get(route('bid.documents.file', ['document' => $document, 'download' => 1]))
            ->assertDownload($document->original_name);
    }

    public function test_exclusao_remove_arquivo_do_disco(): void
    {
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload());
        $document = BidDocument::firstOrFail();
        $path = $document->file_path;

        $this->actingAs($this->admin)->delete(route('bid.documents.destroy', $document))->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted('bid_documents', ['id' => $document->id]);
    }

    public function test_painel_conta_permanentes_como_validos(): void
    {
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload());
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload([
            'name' => 'Contrato social',
            'no_expiry' => 1,
            'expires_at' => null,
            'bid_document_type_id' => BidDocumentType::where('slug', 'contrato_social')->value('id'),
            'bid_document_category_id' => BidDocumentCategory::where('slug', 'juridica')->value('id'),
            'arquivo' => UploadedFile::fake()->create('contrato.pdf', 50, 'application/pdf'),
        ]));

        $counters = app(\App\Services\Bid\BidDashboardService::class)->counters();

        $this->assertSame(2, $counters['total']);
        $this->assertSame(2, $counters['validos']);
        $this->assertSame(0, $counters['vencendo']);
        $this->assertSame(0, $counters['vencidos']);
    }

    public function test_abas_de_status_filtram_o_acervo(): void
    {
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload([
            'name' => 'CND Federal vencida', 'expires_at' => Carbon::today()->subDays(5)->toDateString(),
        ]));
        $this->actingAs($this->admin)->post(route('bid.documents.store', $this->company), $this->payload([
            'name' => 'CNDT vencendo',
            'expires_at' => Carbon::today()->addDays(10)->toDateString(),
            'bid_document_type_id' => BidDocumentType::where('slug', 'cndt')->value('id'),
            'bid_document_category_id' => BidDocumentCategory::where('slug', 'trabalhista')->value('id'),
        ]));

        $this->actingAs($this->admin)
            ->get(route('bid.companies.show', ['company' => $this->company, 'status' => 'vencido']))
            ->assertOk()
            ->assertSee('CND Federal vencida')
            ->assertDontSee('CNDT vencendo');

        $this->actingAs($this->admin)
            ->get(route('bid.companies.show', ['company' => $this->company, 'status' => 'vencendo']))
            ->assertOk()
            ->assertSee('CNDT vencendo')
            ->assertDontSee('CND Federal vencida');
    }
}
