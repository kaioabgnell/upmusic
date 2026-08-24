<?php

namespace Tests\Feature\External;

use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\ExternalForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Nome do solicitante" no formulário público (specs/11): é obrigatório no envio, fica gravado no
 * submission e volta no JSON do card para o modal exibir — é por ele que a equipe sabe de quem cobrar.
 */
class ExternalFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function form(): ExternalForm
    {
        $board = Board::create(['name' => 'Produção']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Análise', 'position' => 1]);

        return ExternalForm::create([
            'board_id' => $board->id,
            'target_column_id' => $column->id,
            'token' => 'tok-teste',
            'title' => 'Envio de NF',
            'active' => true,
        ]);
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

    public function test_solicitante_e_obrigatorio(): void
    {
        Storage::fake('local');
        $form = $this->form();

        $this->post(route('external.form.submit', $form->token), $this->payload(['requester_name' => '']))
            ->assertSessionHasErrors('requester_name');

        $this->assertSame(0, $form->submissions()->count());
    }

    public function test_solicitante_e_gravado_e_volta_no_json_do_card(): void
    {
        Storage::fake('local');
        $form = $this->form();

        $this->post(route('external.form.submit', $form->token), $this->payload())
            ->assertRedirect(route('external.form.success', $form->token));

        $submission = $form->submissions()->firstOrFail();
        $this->assertSame('Carla Produtora', $submission->requester_name);
        $this->assertNotNull($submission->card_id);

        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();

        $this->actingAs($admin)
            ->getJson(route('cards.show', $submission->card_id))
            ->assertOk()
            ->assertJsonPath('requester_name', 'Carla Produtora');
    }

    /**
     * A nota fiscal é opcional. O `required` num input com `display:none` fazia o Chrome abortar o submit
     * inteiro ("An invalid form control with name='invoice' is not focusable"), então o campo não é
     * obrigatório nem no HTML nem na validação.
     */
    public function test_envio_sem_nota_fiscal_cria_card_sem_anexo(): void
    {
        Storage::fake('local');
        $form = $this->form();

        $payload = $this->payload();
        unset($payload['invoice']);

        $this->post(route('external.form.submit', $form->token), $payload)
            ->assertRedirect(route('external.form.success', $form->token));

        $submission = $form->submissions()->firstOrFail();
        $this->assertNull($submission->invoice_path);
        $this->assertSame(0, Card::findOrFail($submission->card_id)->attachments()->count());
    }

    public function test_envio_com_nota_fiscal_anexa_ao_card(): void
    {
        Storage::fake('local');
        $form = $this->form();

        $this->post(route('external.form.submit', $form->token), $this->payload())
            ->assertRedirect(route('external.form.success', $form->token));

        $submission = $form->submissions()->firstOrFail();
        $this->assertNotNull($submission->invoice_path);
        Storage::disk('local')->assertExists($submission->invoice_path);

        $attachment = Card::findOrFail($submission->card_id)->attachments()->sole();
        $this->assertSame(AttachmentKind::NotaFiscal->value, $attachment->kind->value);
        $this->assertSame('nf.pdf', $attachment->original_name);
    }

    /** O input de arquivo não pode voltar a ser `required` escondido — foi o que travava o submit. */
    public function test_input_de_nota_fiscal_nao_e_required_e_e_focavel(): void
    {
        $form = $this->form();

        $html = $this->get(route('external.form.show', $form->token))->assertOk()->getContent();

        preg_match('/<input[^>]*name="invoice"[^>]*>/', $html, $m);
        $this->assertNotEmpty($m, 'input de nota fiscal não encontrado no formulário público');
        $this->assertStringNotContainsString('required', $m[0]);
        $this->assertStringNotContainsString('class="hidden"', $m[0]);
    }

    /** Formato acordado com a equipe: CNPJ + empresa no título, dados do envio rotulados na descrição. */
    public function test_titulo_e_descricao_do_card_seguem_o_formato_do_envio(): void
    {
        Storage::fake('local');
        $form = $this->form();

        $this->post(route('external.form.submit', $form->token), $this->payload())
            ->assertRedirect(route('external.form.success', $form->token));

        $card = Card::findOrFail($form->submissions()->firstOrFail()->card_id);

        $this->assertSame('11.222.333/0001-81 - Som & Luz Ltda', $card->title);
        $this->assertSame(
            "Valor (R$) - 1.500,00\n".
            "Descrição do serviço - Locação de PA\n".
            'Dados para pagamento - PIX 11222333000181',
            $card->description
        );
    }

    public function test_card_criado_na_mao_nao_tem_solicitante(): void
    {
        $form = $this->form();
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();

        $card = $form->board->cards()->create([
            'board_column_id' => $form->target_column_id,
            'title' => 'Card manual',
        ]);

        $this->actingAs($admin)
            ->getJson(route('cards.show', $card))
            ->assertOk()
            ->assertJsonPath('requester_name', null);
    }
}
