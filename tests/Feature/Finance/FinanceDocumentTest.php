<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\CreateFinanceDocument;
use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\FinanceDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Controle documental (specs/23 §4.5 e §6.6). */
class FinanceDocumentTest extends FinanceTestCase
{
    public function test_documento_precisa_referenciar_anexo_ou_ter_arquivo_proprio(): void
    {
        $item = $this->sheet()->costItems()->create(['description' => 'Taxa ECAD']);

        $invalid = new FinanceDocument([
            'finance_cost_item_id' => $item->id,
            'kind' => FinanceDocumentKind::Boleto,
        ]);

        $this->expectException(InvalidArgumentException::class);
        CreateFinanceDocument::assertValid($invalid);
    }

    public function test_upload_direto_guarda_arquivo_proprio(): void
    {
        Storage::fake('local');
        $item = $this->sheet()->costItems()->create(['description' => 'Guia DUAM']);

        $document = app(CreateFinanceDocument::class)->fromUpload(
            $item,
            UploadedFile::fake()->create('guia.pdf', 12, 'application/pdf'),
            FinanceDocumentKind::Boleto,
            $this->user(),
        );

        $this->assertNull($document->card_attachment_id);
        $this->assertNotNull($document->path);
        Storage::disk('local')->assertExists($document->path);
        CreateFinanceDocument::assertValid($document);
    }

    public function test_arquivo_do_financeiro_e_servido_com_nosniff(): void
    {
        Storage::fake('local');
        $item = $this->sheet()->costItems()->create(['description' => 'Guia DUAM']);

        $document = app(CreateFinanceDocument::class)->fromUpload(
            $item,
            UploadedFile::fake()->create('guia.pdf', 12, 'application/pdf'),
            FinanceDocumentKind::Boleto,
        );

        $this->actingAs($this->user())
            ->get(route('finance.documents.show', $document))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_excluir_anexo_do_card_e_bloqueado_com_prestacao_de_contas_fechada(): void
    {
        Storage::fake('local');
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $attachment = $card->attachments()->create([
            'kind' => AttachmentKind::Comprovante->value,
            'original_name' => 'comprovante.pdf',
            'path' => 'card-attachments/1/comprovante.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
        ]);

        $item = $this->sheet($event)->costItems()->create(['description' => 'Som', 'card_id' => $card->id]);
        app(CreateFinanceDocument::class)->fromAttachment($item, $attachment, FinanceDocumentKind::Comprovante);

        $this->sheet($event)->update(['status' => 'fechado']);

        $this->actingAs($this->user())
            ->deleteJson(route('cards.attachments.destroy', $attachment))
            ->assertStatus(422);

        $this->assertDatabaseHas('card_attachments', ['id' => $attachment->id]);
    }

    public function test_excluir_anexo_com_planilha_aberta_remove_o_documento_junto(): void
    {
        Storage::fake('local');
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $attachment = $card->attachments()->create([
            'kind' => AttachmentKind::Comprovante->value,
            'original_name' => 'comprovante.pdf',
            'path' => 'card-attachments/1/comprovante.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
        ]);

        $item = $this->sheet($event)->costItems()->create(['description' => 'Som', 'card_id' => $card->id]);
        app(CreateFinanceDocument::class)->fromAttachment($item, $attachment, FinanceDocumentKind::Comprovante);

        $this->actingAs($this->user())
            ->deleteJson(route('cards.attachments.destroy', $attachment))
            ->assertOk();

        // cascadeOnDelete: o vínculo some com o anexo (o arquivo é o mesmo, não uma cópia).
        $this->assertSame(0, FinanceDocument::count());
    }
}
