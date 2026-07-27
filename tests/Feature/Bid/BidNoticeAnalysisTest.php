<?php

namespace Tests\Feature\Bid;

use App\Domain\Enums\BidMatchStatus;
use App\Domain\Enums\BidNoticeStatus;
use App\Domain\Enums\BidVerdict;
use App\Domain\Enums\UserRole;
use App\Models\BidAiCall;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;
use App\Models\BidNotice;
use App\Models\BidNoticeRequirement;
use App\Models\BidRequirementMatch;
use App\Models\User;
use Database\Seeders\BidCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Análise de edital de ponta a ponta com a IA mockada (specs/21 §5.1, §5.2, §11 e §14).
 * A chave real nunca é usada: todo o tráfego HTTP é interceptado por `Http::fake`.
 */
class BidNoticeAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BidCompany $forte;

    private BidCompany $fraca;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BidCatalogSeeder::class);
        config(['services.gemini.key' => 'chave-de-teste', 'services.gemini.attempts' => 1]);

        $this->admin = User::factory()->create([
            'email' => 'admin@teste.local', 'role' => UserRole::Admin->value, 'active' => true,
        ])->fresh();

        $this->forte = BidCompany::create([
            'corporate_name' => 'UP PORTARIA LTDA', 'cnpj' => '45997418000153', 'size' => 'me',
            'capital_social' => 150000, 'cnaes' => [['code' => '8011101', 'primary' => true]],
        ]);

        $this->fraca = BidCompany::create([
            'corporate_name' => 'UP EVENTOS LTDA', 'cnpj' => '19131243000197', 'size' => 'epp',
            'capital_social' => 10000, 'cnaes' => [['code' => '9001901', 'primary' => true]],
        ]);

        // A empresa forte tem CND Federal e CNDT vigentes; a fraca não tem nada.
        foreach (['cnd_federal', 'cndt'] as $slug) {
            $type = BidDocumentType::where('slug', $slug)->firstOrFail();
            BidDocument::create([
                'bid_company_id' => $this->forte->id,
                'bid_document_category_id' => $type->bid_document_category_id,
                'bid_document_type_id' => $type->id,
                'name' => $type->name,
                'file_path' => "licitacoes/{$this->forte->id}/{$slug}.pdf",
                'original_name' => "{$slug}.pdf",
                'mime_type' => 'application/pdf',
                'expires_at' => Carbon::today()->addDays(150),
            ]);
        }
    }

    /** Resposta padrão do Gemini no formato do responseSchema (specs/21 §11.2). */
    private function geminiPayload(array $overrides = []): array
    {
        $base = [
            'notice' => [
                'title' => 'Pregão Eletrônico 45/2026',
                'agency' => 'Prefeitura de Campinas',
                'number' => '45/2026',
                'modality' => 'Pregão Eletrônico',
                'uf' => 'SP',
                'city' => 'Campinas',
                'object_summary' => 'Serviços continuados de portaria e vigilância desarmada.',
                'estimated_value' => 1240000,
                'session_at' => '2026-08-12 14:00:00',
                'me_epp_exclusive' => false,
            ],
            'requirements' => [
                [
                    'kind' => 'documento', 'name' => 'CND Federal', 'type_slug' => 'cnd_federal',
                    'category_slug' => 'fiscal', 'mandatory' => true,
                    'source_excerpt' => 'Certidão Negativa de Débitos Federais', 'source_page' => 3,
                ],
                [
                    'kind' => 'documento', 'name' => 'CNDT', 'type_slug' => 'cndt',
                    'category_slug' => 'trabalhista', 'mandatory' => true,
                    'source_excerpt' => 'Certidão Negativa de Débitos Trabalhistas', 'source_page' => 3,
                ],
                [
                    'kind' => 'cnae', 'name' => 'CNAE compatível', 'mandatory' => true,
                    'expected_cnae' => '8011-1/01', 'source_excerpt' => 'CNAE 8011-1/01', 'source_page' => 4,
                ],
                [
                    'kind' => 'capital_social', 'name' => 'Capital social mínimo', 'mandatory' => true,
                    'expected_percent_of_estimate' => 10,
                    'source_excerpt' => 'capital social mínimo de 10% do valor estimado', 'source_page' => 4,
                ],
            ],
            'confidence' => 0.93,
            'warnings' => [],
        ];

        // `notice` é mesclado campo a campo; `requirements` é SUBSTITUÍDO (mesclar listas por
        // índice deixaria requisitos do payload padrão sobrando).
        $payload = $base;
        $payload['notice'] = array_replace($base['notice'], $overrides['notice'] ?? []);

        foreach ($overrides as $key => $value) {
            if ($key !== 'notice') {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function fakeGemini(array $payload, int $status = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
                'usageMetadata' => ['promptTokenCount' => 1500, 'candidatesTokenCount' => 400, 'totalTokenCount' => 1900],
            ], $status),
        ]);
    }

    private function analyze(array $extra = [])
    {
        return $this->actingAs($this->admin)->postJson(route('bid.notices.store'), array_merge([
            'arquivo' => UploadedFile::fake()->create('edital.pdf', 300, 'application/pdf'),
        ], $extra));
    }

    public function test_analise_extrai_edital_requisitos_e_monta_ranking(): void
    {
        $this->fakeGemini($this->geminiPayload());

        $this->analyze()->assertOk()->assertJson(['ok' => true]);

        $notice = BidNotice::firstOrFail();
        $this->assertSame(BidNoticeStatus::Analisado, $notice->status);
        $this->assertSame('Pregão Eletrônico 45/2026', $notice->title);
        $this->assertSame('1240000.00', $notice->estimated_value);
        $this->assertSame('12/08/2026 14:00', $notice->session_at->format('d/m/Y H:i'));
        $this->assertCount(4, $notice->requirements);

        // Ranking: a empresa com CNAE, capital e certidões em ordem fica em primeiro.
        $ranking = $notice->evaluations()->orderBy('rank')->with('company')->get();
        $this->assertSame($this->forte->id, $ranking->first()->bid_company_id);
        $this->assertSame(BidVerdict::Apta, $ranking->first()->verdict);
        $this->assertSame(BidVerdict::Inapta, $ranking->last()->verdict);

        // A empresa sem documentos e com CNAE/capital incompatíveis tem bloqueios explicados.
        $blockers = $ranking->last()->blockers;
        $this->assertNotEmpty($blockers);
        $this->assertStringContainsString('CNAE', implode(' ', $blockers));

        // Afinidade de objeto e requisitos estruturais aparecem como pontos favoráveis.
        $this->assertStringContainsString('CNAE 8011-1/01', implode(' ', $ranking->first()->highlights));

        // Rastreabilidade: todo requisito guarda o trecho de origem.
        $this->assertNotEmpty($notice->requirements->pluck('source_excerpt')->filter());

        // Log de custo gravado.
        $call = BidAiCall::firstOrFail();
        $this->assertTrue($call->success);
        $this->assertSame(1900, $call->total_tokens);
        $this->assertSame('edital', $call->type);
    }

    public function test_requisicao_usa_schema_thinking_low_e_chave_no_header(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            $this->assertSame('chave-de-teste', $request->header('X-goog-api-key')[0]);
            $this->assertSame('application/json', $body['generationConfig']['responseMimeType']);
            $this->assertSame('low', $body['generationConfig']['thinkingConfig']['thinkingLevel']);
            $this->assertArrayHasKey('responseSchema', $body['generationConfig']);
            // O PDF vai inline, em base64.
            $this->assertArrayHasKey('inline_data', $body['contents'][0]['parts'][0]);
            $this->assertSame('application/pdf', $body['contents'][0]['parts'][0]['inline_data']['mime_type']);

            return true;
        });
    }

    public function test_json_invalido_deixa_a_analise_em_erro_reprocessavel(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'isto não é json']]]]],
            ]),
        ]);

        $this->analyze()->assertStatus(422)->assertJsonPath('ok', false);

        $notice = BidNotice::firstOrFail();
        $this->assertSame(BidNoticeStatus::Erro, $notice->status);
        $this->assertStringContainsString('formato inesperado', $notice->error_message);
        // A resposta bruta fica guardada para diagnóstico.
        $this->assertSame('isto não é json', $notice->raw_response);
        $this->assertFalse(BidAiCall::firstOrFail()->success);
    }

    public function test_limite_do_gemini_devolve_mensagem_especifica(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429)]);

        $this->analyze()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Limite de uso da IA atingido. Tente novamente em alguns minutos.');

        $this->assertSame(BidNoticeStatus::Erro, BidNotice::firstOrFail()->status);
    }

    public function test_chave_ausente_nao_chega_a_chamar_a_api(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->analyze()
            ->assertStatus(422)
            ->assertJsonPath('message', 'Integração de IA indisponível — verifique a configuração do sistema.');

        Http::assertNothingSent();
    }

    public function test_indisponibilidade_temporaria_e_repetida_antes_de_falhar(): void
    {
        // 503 é o "pico de demanda" do Google: sem worker, a nova tentativa é a única chance.
        config(['services.gemini.attempts' => 2]);

        Http::fakeSequence()
            ->push(['error' => ['code' => 503]], 503)
            ->push([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($this->geminiPayload())]]]]],
                'usageMetadata' => ['totalTokenCount' => 100],
            ], 200);

        $this->analyze()->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(BidNoticeStatus::Analisado, BidNotice::firstOrFail()->status);
        Http::assertSentCount(2);
    }

    public function test_saida_da_ia_e_saneada(): void
    {
        $this->fakeGemini($this->geminiPayload([
            'notice' => [
                'title' => '<script>alert(1)</script>Edital com HTML',
                'estimated_value' => -500,
                'uf' => 'sao paulo',
            ],
            'requirements' => [
                [
                    'kind' => 'inventado', 'name' => str_repeat('A', 400), 'category_slug' => 'inexistente',
                    'mandatory' => true, 'source_excerpt' => str_repeat('B', 2000),
                ],
                // Sem trecho de origem: descartado, porque não há como auditar.
                ['kind' => 'documento', 'name' => 'Requisito fantasma', 'mandatory' => true, 'source_excerpt' => ''],
                // Sem nome: descartado.
                ['kind' => 'documento', 'name' => '', 'mandatory' => true, 'source_excerpt' => 'trecho'],
            ],
        ]));

        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        $this->assertStringNotContainsString('<script>', $notice->title);
        $this->assertStringContainsString('Edital com HTML', $notice->title);
        $this->assertNull($notice->estimated_value, 'valor negativo é descartado');
        $this->assertSame('SA', $notice->uf, 'UF é truncada em 2 caracteres');

        $requirements = $notice->requirements;
        $this->assertCount(1, $requirements, 'requisitos sem nome ou sem trecho são descartados');

        $requirement = $requirements->first();
        $this->assertSame(200, mb_strlen($requirement->name));
        $this->assertSame(1000, mb_strlen($requirement->source_excerpt));
        // `kind` desconhecido cai em "outro", que exige conferência humana.
        $this->assertSame('outro', $requirement->kind->value);
        $this->assertSame('outros', $requirement->category->slug);
    }

    public function test_reprocessar_analise_interrompida_nao_duplica_dados(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        $requirementCount = $notice->requirements()->count();

        // Simula a análise interrompida: processando e sem atualização recente (§5.2).
        $notice->forceFill([
            'status' => BidNoticeStatus::Processando,
            'updated_at' => now()->subHour(),
        ])->saveQuietly();

        $this->assertTrue($notice->fresh()->is_stale);
        $this->actingAs($this->admin)->get(route('bid.notices.show', $notice))
            ->assertOk()
            ->assertSee('Análise interrompida');

        $this->actingAs($this->admin)
            ->postJson(route('bid.notices.reprocess', $notice))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $notice->refresh();
        $this->assertSame(BidNoticeStatus::Analisado, $notice->status);
        $this->assertSame($requirementCount, $notice->requirements()->count());
        $this->assertSame(2, $notice->evaluations()->count());
    }

    public function test_override_manual_sobrevive_ao_recalculo_e_historico_e_imutavel(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        $evaluation = $notice->evaluations()->where('bid_company_id', $this->fraca->id)->firstOrFail();
        $verdictAtAnalysis = $evaluation->verdict_at_analysis;
        $scoreAtAnalysis = $evaluation->score_at_analysis;

        $requirement = $notice->requirements()->where('kind', 'documento')->firstOrFail();
        $match = BidRequirementMatch::where('bid_notice_requirement_id', $requirement->id)
            ->where('bid_company_id', $this->fraca->id)
            ->firstOrFail();

        $this->assertSame(BidMatchStatus::Ausente, $match->status);

        $this->actingAs($this->admin)
            ->put(route('bid.matches.update', $match), [
                'status' => BidMatchStatus::Atendido->value,
                'reason' => 'Certidão entregue em mãos, confirmada no portal',
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertTrue($match->manual_override);
        $this->assertSame($this->admin->id, $match->overridden_by);

        // Recálculo determinístico não desfaz a decisão humana.
        $this->actingAs($this->admin)->post(route('bid.notices.reevaluate', $notice))->assertRedirect();

        $match->refresh();
        $this->assertTrue($match->manual_override);
        $this->assertSame(BidMatchStatus::Atendido, $match->status);

        // O veredito congelado na análise permanece — é a base do relatório histórico.
        $evaluation->refresh();
        $this->assertSame($verdictAtAnalysis, $evaluation->verdict_at_analysis);
        $this->assertSame($scoreAtAnalysis, $evaluation->score_at_analysis);
    }

    public function test_ignorar_requisito_recalcula_o_veredito(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        $cnae = $notice->requirements()->where('kind', 'cnae')->firstOrFail();

        $this->assertSame(
            BidVerdict::Inapta,
            $notice->evaluations()->where('bid_company_id', $this->fraca->id)->value('verdict')
        );

        $this->actingAs($this->admin)->put(route('bid.requirements.update', $cnae), [
            'name' => $cnae->name,
            'kind' => $cnae->kind->value,
            'mandatory' => 1,
            'ignored' => 1,
            'ignored_reason' => 'Objeto social já cobre a atividade',
        ])->assertRedirect();

        $match = BidRequirementMatch::where('bid_notice_requirement_id', $cnae->id)
            ->where('bid_company_id', $this->fraca->id)
            ->firstOrFail();

        $this->assertSame(BidMatchStatus::NaoAplicavel, $match->status);
        // Segue inapta por outros motivos (documentos e capital), mas o bloqueio de CNAE saiu.
        $blockers = $notice->evaluations()->where('bid_company_id', $this->fraca->id)->firstOrFail()->blockers;
        $this->assertStringNotContainsString('CNAE', implode(' ', $blockers ?? []));
    }

    public function test_acervo_alterado_dispara_recalculo_ao_abrir_a_analise(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        $this->assertSame(BidVerdict::Apta, $notice->evaluations()->where('bid_company_id', $this->forte->id)->value('verdict'));

        // A CND Federal vence: ao abrir a análise, a aptidão precisa refletir o acervo atual.
        BidDocument::where('bid_company_id', $this->forte->id)
            ->whereHas('type', fn ($q) => $q->where('slug', 'cnd_federal'))
            ->update(['expires_at' => Carbon::today()->subDay(), 'updated_at' => now()->addSecond()]);

        $this->actingAs($this->admin)->get(route('bid.notices.show', $notice))
            ->assertOk()
            ->assertSee('recalculada agora');

        $this->assertSame(
            BidVerdict::Inapta,
            $notice->evaluations()->where('bid_company_id', $this->forte->id)->value('verdict')
        );
    }

    public function test_analise_por_texto_colado_dispensa_arquivo(): void
    {
        $this->fakeGemini($this->geminiPayload());

        $this->actingAs($this->admin)->postJson(route('bid.notices.store'), [
            'raw_text' => str_repeat('Cláusula de habilitação do edital. ', 20),
        ])->assertOk();

        $notice = BidNotice::firstOrFail();
        $this->assertSame('texto', $notice->source->value);
        $this->assertNull($notice->file_path);

        Http::assertSent(fn (Request $request) => isset($request->data()['contents'][0]['parts'][0]['text']));
    }

    public function test_texto_curto_e_recusado(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->postJson(route('bid.notices.store'), ['raw_text' => 'muito curto'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('raw_text');

        Http::assertNothingSent();
        $this->assertDatabaseCount('bid_notices', 0);
    }

    public function test_teto_diario_de_ia_protege_o_orcamento(): void
    {
        config(['licitacoes.ai_daily_limit' => 1]);
        $this->fakeGemini($this->geminiPayload());

        $this->analyze()->assertOk();
        $this->analyze()->assertStatus(422)->assertJsonPath('message', 'Você atingiu o limite de 1 leituras de IA hoje. Tente novamente amanhã.');

        Http::assertSentCount(1);
    }

    public function test_leitura_assistida_de_documento_sugere_campos_sem_persistir(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'name' => 'Certidão Negativa de Débitos Federais',
                    'type_slug' => 'cnd_federal',
                    'category_slug' => 'outros', // o tipo canônico corrige para fiscal
                    'issued_at' => '2026-07-10',
                    'expires_at' => '2027-01-06',
                    'control_code' => '8A2F.91BC',
                    'company_cnpj' => '11.222.333/0001-44', // CNPJ de outra empresa
                    'confidence' => 0.9,
                ])]]]]],
            ]),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('bid.documents.read'), [
            'arquivo' => UploadedFile::fake()->create('cnd.pdf', 80, 'application/pdf'),
            'bid_company_id' => $this->forte->id,
        ])->assertOk();

        $suggestion = $response->json('suggestion');
        $this->assertSame('cnd_federal', $suggestion['type_slug']);
        $this->assertSame('fiscal', $suggestion['category_slug'], 'o tipo canônico manda na categoria');
        $this->assertSame('2027-01-06', $suggestion['expires_at']);
        $this->assertSame('8A2F.91BC', $suggestion['control_code']);
        $this->assertNotEmpty($suggestion['warnings'], 'CNPJ divergente precisa gerar aviso');

        // Nada é gravado: a IA sugere, o usuário confirma.
        $this->assertSame(2, BidDocument::count());
    }

    public function test_falha_na_leitura_assistida_nao_impede_cadastro_manual(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $this->actingAs($this->admin)
            ->postJson(route('bid.documents.read'), [
                'arquivo' => UploadedFile::fake()->create('cnd.pdf', 80, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        // O caminho manual continua disponível.
        $this->actingAs($this->admin)
            ->post(route('bid.documents.store', $this->forte), [
                'name' => 'CND Estadual',
                'bid_document_category_id' => BidDocumentCategory::where('slug', 'fiscal')->value('id'),
                'bid_document_type_id' => BidDocumentType::where('slug', 'cnd_estadual')->value('id'),
                'expires_at' => Carbon::today()->addDays(90)->toDateString(),
                'control_code' => 'EST-1',
                'no_expiry' => 0,
                'arquivo' => UploadedFile::fake()->create('estadual.pdf', 80, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, BidDocument::count());
    }

    public function test_matriz_em_csv_lista_requisitos_por_empresa(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        $csv = $this->actingAs($this->admin)->get(route('bid.notices.matrix', $notice))->getContent();

        $this->assertStringContainsString('Requisito;Natureza;Obrigatorio', $csv);
        $this->assertStringContainsString('UP PORTARIA LTDA', $csv);
        $this->assertStringContainsString('CND Federal', $csv);
    }

    public function test_plano_de_regularizacao_lista_o_que_falta(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('bid.notices.plan', ['notice' => $notice, 'company' => $this->fraca]))
            ->assertOk()
            ->assertSee('Bloqueia a participação')
            ->assertSee('CND Federal');
    }

    public function test_relatorio_usa_o_veredito_congelado_na_analise(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $notice = BidNotice::firstOrFail();
        // Muda o presente: a empresa forte perde a CND e é recalculada.
        BidDocument::where('bid_company_id', $this->forte->id)->delete();
        $this->actingAs($this->admin)->post(route('bid.notices.reevaluate', $notice))->assertRedirect();

        $evaluation = $notice->evaluations()->where('bid_company_id', $this->forte->id)->firstOrFail();
        $this->assertSame(BidVerdict::Inapta, $evaluation->verdict);
        $this->assertSame(BidVerdict::Apta, $evaluation->verdict_at_analysis);

        $this->actingAs($this->admin)->get(route('bid.reports.index'))
            ->assertOk()
            ->assertSee('Histórico de análises');

        $csv = $this->actingAs($this->admin)->get(route('bid.reports.export'))->getContent();
        $this->assertStringContainsString('Apta', $csv, 'o CSV histórico traz o veredito da época');
    }

    public function test_requisitos_recebem_ordem_e_pagina_de_origem(): void
    {
        $this->fakeGemini($this->geminiPayload());
        $this->analyze()->assertOk();

        $requirements = BidNoticeRequirement::orderBy('sort_order')->get();

        $this->assertSame([1, 2, 3, 4], $requirements->pluck('sort_order')->all());
        $this->assertSame(3, $requirements->first()->source_page);
    }
}
