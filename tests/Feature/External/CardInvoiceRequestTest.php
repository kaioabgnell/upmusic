<?php

namespace Tests\Feature\External;

use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\CardInvoiceRequest;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Compartilhar Solicitação de Nota": link público gerado a partir de um card. Mesmo formulário do
 * specs/11, mas o envio entra no card de origem — anexo + histórico — e NUNCA cria card novo.
 */
class CardInvoiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();
    }

    private function card(array $attributes = []): Card
    {
        $board = Board::create(['name' => 'Produção']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Aguardando NF', 'position' => 1]);

        return Card::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Locação de PA',
            'actual_value' => 1500,
        ] + $attributes);
    }

    private function link(Card $card): string
    {
        $url = $this->actingAs($this->user())
            ->postJson(route('invoice-request.generate', $card))
            ->assertOk()
            ->json('url');

        auth()->logout();

        return basename($url);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cnpj' => '11.222.333/0001-81',
            'name' => 'Som & Luz Ltda',
            'requester_name' => 'Carla Produtora',
            'value' => '1.500,00',
            'service_date' => '2026-08-20',
            'service_description' => 'Locação de PA',
            'payment_data' => 'PIX 11222333000181',
            'invoice' => UploadedFile::fake()->create('nf.pdf', 12, 'application/pdf'),
        ], $overrides);
    }

    public function test_envio_entra_no_card_de_origem_sem_criar_card_novo(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $token = $this->link($card);
        $cardsAntes = Card::count();

        $this->post(route('invoice-request.submit', $token), $this->payload())
            ->assertRedirect(route('invoice-request.success', $token));

        $this->assertSame($cardsAntes, Card::count());

        $anexo = $card->attachments()->sole();
        $this->assertSame(AttachmentKind::NotaFiscal, $anexo->kind);
        $this->assertSame('nf.pdf', $anexo->original_name);
        Storage::disk('local')->assertExists($anexo->path);
    }

    public function test_dados_do_envio_ficam_no_historico_do_card(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload());

        $comentario = $card->comments()->sole()->body;
        $this->assertStringContainsString('Carla Produtora', $comentario);
        $this->assertStringContainsString('1.500,00', $comentario);
        $this->assertStringContainsString('PIX 11222333000181', $comentario);
        $this->assertStringContainsString('nf.pdf', $comentario);
    }

    /** Terceiro de fora não reescreve o que foi negociado — título e valores do card ficam. */
    public function test_envio_nao_sobrescreve_titulo_nem_valores_do_card(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload(['value' => '99.999,00']));

        $card->refresh();
        $this->assertSame('Locação de PA', $card->title);
        $this->assertEquals(1500, (float) $card->actual_value);
    }

    public function test_card_sem_empresa_ganha_a_empresa_do_cnpj(): void
    {
        Storage::fake('local');
        $empresa = Empresa::create(['corporate_name' => 'Som & Luz Ltda', 'document' => '11222333000181']);
        $card = $this->card();
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload());

        $this->assertSame($empresa->id, $card->refresh()->empresa_id);
    }

    public function test_envio_sem_nota_fiscal_registra_dados_sem_anexo(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload(['invoice' => null]))
            ->assertRedirect(route('invoice-request.success', $token));

        $this->assertSame(0, $card->attachments()->count());
        $this->assertStringContainsString('sem nota fiscal', $card->comments()->sole()->body);
    }

    /** Reenviar o link para a mesma pessoa não pode invalidar o que ela já recebeu. */
    public function test_link_e_unico_e_estavel_por_card(): void
    {
        $card = $this->card();

        $this->assertSame($this->link($card), $this->link($card));
        $this->assertSame(1, CardInvoiceRequest::count());
    }

    public function test_formulario_publico_abre_sem_login_e_vem_com_a_empresa_do_card(): void
    {
        $empresa = Empresa::create(['corporate_name' => 'Som & Luz Ltda', 'document' => '11222333000181']);
        $card = $this->card(['empresa_id' => $empresa->id]);
        $token = $this->link($card);

        $this->get(route('invoice-request.show', $token))
            ->assertOk()
            ->assertSee(route('invoice-request.submit', $token), false)
            ->assertSee('11.222.333/0001-81')
            ->assertSee('Som &amp; Luz Ltda', false);
    }

    public function test_link_desativado_ou_de_card_excluido_nao_abre(): void
    {
        $card = $this->card();
        $token = $this->link($card);

        $card->delete();
        $this->get(route('invoice-request.show', $token))->assertNotFound();

        $outro = $this->card();
        $tokenOutro = $this->link($outro);
        CardInvoiceRequest::where('card_id', $outro->id)->update(['active' => false]);
        $this->get(route('invoice-request.show', $tokenOutro))->assertNotFound();
    }

    public function test_honeypot_finge_sucesso_e_nao_toca_no_card(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload(['website' => 'http://spam']))
            ->assertRedirect(route('invoice-request.success', $token));

        $this->assertSame(0, $card->attachments()->count());
        $this->assertSame(0, $card->comments()->count());
    }

    /**
     * O caso que motivou a feature: o card já está no Financeiro e falta a NF. Ela chega pelo link
     * e vira documento de controle da linha sozinha — ninguém sobe o arquivo de novo no financeiro.
     */
    public function test_nf_recebida_em_card_ja_no_financeiro_vira_documento_da_linha(): void
    {
        Storage::fake('local');
        $event = \App\Models\Event::create(['name' => 'Festa', 'start_date' => '2026-06-20', 'end_date' => '2026-06-21', 'active' => true]);
        $card = $this->card(['event_id' => $event->id]);
        $item = app(\App\Actions\Finance\SyncCardToFinance::class)->execute($card->fresh());
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload());

        $this->assertSame(
            \App\Domain\Enums\FinanceDocumentKind::NotaFiscal,
            $item->documents()->sole()->kind,
        );
    }

    /**
     * O pedido desta melhoria: além do que já era feito (anexo + histórico), o envio avança o card
     * para a próxima coluna do quadro sozinho — mesmo efeito automático já usado na minuta do
     * fornecedor (specs/19).
     */
    public function test_envio_avanca_o_card_para_a_proxima_coluna(): void
    {
        Storage::fake('local');
        $board = Board::create(['name' => 'Produção']);
        $aguardando = BoardColumn::create(['board_id' => $board->id, 'name' => 'Aguardando NF', 'position' => 1]);
        $conferencia = BoardColumn::create(['board_id' => $board->id, 'name' => 'Em conferência', 'position' => 2]);
        $card = Card::create(['board_id' => $board->id, 'board_column_id' => $aguardando->id, 'title' => 'Locação de PA']);
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload());

        $card->refresh();
        $this->assertSame($conferencia->id, $card->board_column_id);

        $movimento = $card->movements()->sole();
        $this->assertSame(\App\Domain\Enums\MovementType::NotaRecebida, $movimento->type);
        $this->assertNull($movimento->user_id);
        $this->assertSame($aguardando->id, $movimento->from_column_id);
        $this->assertSame($conferencia->id, $movimento->to_column_id);
    }

    /** Envio sem nota fiscal (ela é opcional) avança o card do mesmo jeito: a resposta ao pedido já conta. */
    public function test_envio_sem_nota_fiscal_tambem_avanca_o_card(): void
    {
        Storage::fake('local');
        $board = Board::create(['name' => 'Produção']);
        $aguardando = BoardColumn::create(['board_id' => $board->id, 'name' => 'Aguardando NF', 'position' => 1]);
        $conferencia = BoardColumn::create(['board_id' => $board->id, 'name' => 'Em conferência', 'position' => 2]);
        $card = Card::create(['board_id' => $board->id, 'board_column_id' => $aguardando->id, 'title' => 'Locação de PA']);
        $token = $this->link($card);

        $this->post(route('invoice-request.submit', $token), $this->payload(['invoice' => null]));

        $this->assertSame($conferencia->id, $card->refresh()->board_column_id);
    }

    /** Card já na última coluna: não há para onde avançar — fica como está, sem erro. */
    public function test_envio_na_ultima_coluna_nao_quebra_e_card_fica_no_lugar(): void
    {
        Storage::fake('local');
        $card = $this->card(); // helper só com 1 coluna — não há próxima.
        $token = $this->link($card);
        $colunaOriginal = $card->board_column_id;

        $this->post(route('invoice-request.submit', $token), $this->payload())
            ->assertRedirect(route('invoice-request.success', $token));

        $this->assertSame($colunaOriginal, $card->refresh()->board_column_id);
        $this->assertSame(0, $card->movements()->count());
    }

    public function test_menu_do_card_tem_o_item_de_solicitacao_de_nota(): void
    {
        $blade = file_get_contents(resource_path('views/boards/partials/card-panel.blade.php'));

        $this->assertStringContainsString('Compartilhar Solicitação de Nota', $blade);
        $this->assertStringContainsString('shareInvoiceRequest()', $blade);
    }

    /**
     * O modal de "Compartilhar Solicitação de Nota" tem botão próprio de copiar (não o `input:` do
     * SweetAlert2, cujo cursor nasce no fim do texto e rola a URL longa até mostrar só o token do
     * final) e o campo continua readonly: só o botão copia, o texto não é editável.
     */
    public function test_modal_de_compartilhar_tem_botao_de_copiar_e_campo_bloqueado(): void
    {
        $js = file_get_contents(resource_path('js/card-panel.js'));

        $this->assertStringContainsString('id="invoice-request-link" type="text" value="${r.url}" readonly', $js);
        $this->assertStringContainsString('id="invoice-request-copy"', $js);
        $this->assertStringContainsString('fa-solid fa-copy', $js);
        // O valor some no fim da URL se o cursor não voltar pro início após o SweetAlert2 montar o input.
        $this->assertStringContainsString('input.scrollLeft = 0', $js);
        $this->assertStringContainsString('navigator.clipboard.writeText(r.url)', $js);
    }

    /**
     * "Valor (R$)" do formulário público segue a mesma máscara dos valores do card/Financeiro
     * (dígito digitado = centavo) — e não o `$money` do @alpinejs/mask, que embola dígitos puros
     * sem vírgula ("1500" viraria "1.500" em vez de "15,00").
     */
    public function test_campo_valor_usa_a_mascara_de_centavos_compartilhada(): void
    {
        $card = $this->card();
        $token = $this->link($card);

        $html = $this->get(route('invoice-request.show', $token))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/:value="value"\s+@input="value = window\.maskMoneyDigits\(\$event\.target\.value\)"/',
            $html,
        );
        $this->assertStringNotContainsString('name="value" value="', $html);

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("import { maskMoneyDigits } from './money'", $js);
        $this->assertStringContainsString('window.maskMoneyDigits = maskMoneyDigits', $js);
    }

    /** O formulário do quadro (specs/11) continua funcionando com a view agora compartilhada. */
    public function test_formulario_externo_do_quadro_segue_intacto(): void
    {
        $board = Board::create(['name' => 'Produção']);
        $form = \App\Models\ExternalForm::create(['board_id' => $board->id, 'token' => 'tok-quadro', 'title' => 'Envio de NF', 'active' => true]);

        $this->get(route('external.form.show', $form->token))
            ->assertOk()
            ->assertSee(route('external.form.submit', $form->token), false);
    }
}
