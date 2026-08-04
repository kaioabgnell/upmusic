<?php

namespace Tests\Feature\Cards;

use App\Domain\Enums\UserRole;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\CardAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Anexo do card abre no navegador em vez de baixar, quando o tipo é exibível.
 *
 * O ponto sensível é o `Content-Type`: ele sai do conteúdo do arquivo, nunca da coluna `mime`
 * (preenchida com o `getClientMimeType()` do upload, que quem envia controla). Servir inline com um
 * tipo forjado seria XSS armazenado na origem do sistema.
 */
class AttachmentDispositionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value, 'active' => true])->fresh();
    }

    private function card(): Card
    {
        $board = Board::create(['name' => 'Comercial']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Entrada', 'position' => 1]);

        return Card::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Locação de som',
        ]);
    }

    /** Anexa um arquivo real pelo endpoint, para o `mime` gravado ser o do upload de verdade. */
    private function attach(User $user, Card $card, UploadedFile $file): CardAttachment
    {
        $id = $this->actingAs($user)
            ->postJson(route('cards.attachments.store', $card), ['file' => $file, 'kind' => 'geral'])
            ->assertCreated()
            ->json('id');

        return CardAttachment::findOrFail($id);
    }

    /** Segura os arquivos base: o destrutor do fake apaga o temporário assim que ele sai de escopo. */
    private array $keepAlive = [];

    private function pngFile(string $name = 'foto.png', ?string $mimeType = null): UploadedFile
    {
        // UploadedFile::fake()->image() gera um PNG de verdade (GD), então o finfo do servidor
        // devolve image/png — é isso que o teste do mime forjado precisa.
        $file = UploadedFile::fake()->image($name, 10, 10);
        $this->keepAlive[] = $file;

        if ($mimeType === null) {
            return $file;
        }

        return new UploadedFile($file->getPathname(), $name, $mimeType, null, true);
    }

    public function test_pdf_abre_inline_no_navegador(): void
    {
        Storage::fake('local');
        $user = $this->admin();
        $card = $this->card();

        $pdf = UploadedFile::fake()->createWithContent('relatorio.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n%%EOF");
        $attachment = $this->attach($user, $card, $pdf);

        $response = $this->actingAs($user)->get(route('cards.attachments.download', $attachment));

        $response->assertOk();
        $this->assertStringStartsWith('inline;', $response->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_imagem_abre_inline_no_navegador(): void
    {
        Storage::fake('local');
        $user = $this->admin();
        $attachment = $this->attach($user, $this->card(), $this->pngFile());

        $response = $this->actingAs($user)->get(route('cards.attachments.download', $attachment));

        $response->assertOk();
        $this->assertStringStartsWith('inline;', $response->headers->get('content-disposition'));
        $this->assertSame('image/png', $response->headers->get('content-type'));
    }

    public function test_arquivo_nao_exibivel_continua_baixando(): void
    {
        Storage::fake('local');
        $user = $this->admin();

        $docx = UploadedFile::fake()->create(
            'contrato.docx', 12,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $attachment = $this->attach($user, $this->card(), $docx);

        $response = $this->actingAs($user)->get(route('cards.attachments.download', $attachment));

        $response->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('content-disposition'));
    }

    public function test_mime_forjado_no_upload_nao_vira_content_type(): void
    {
        Storage::fake('local');
        $user = $this->admin();

        // PNG de verdade, mas declarado como text/html pelo cliente.
        $attachment = $this->attach($user, $this->card(), $this->pngFile('spoof.png', 'text/html'));

        // O `mime` gravado é o do cliente — por isso ele não pode ser a fonte do cabeçalho.
        $this->assertSame('text/html', $attachment->mime);

        $response = $this->actingAs($user)->get(route('cards.attachments.download', $attachment));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('content-type'));
        $this->assertStringNotContainsString('text/html', (string) $response->headers->get('content-type'));
    }

    public function test_anexo_de_card_sem_acesso_continua_bloqueado(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $attachment = $this->attach($admin, $this->card(), $this->pngFile());

        $estranho = User::factory()->create(['role' => UserRole::Usuario->value, 'active' => true])->fresh();

        $this->actingAs($estranho)
            ->get(route('cards.attachments.download', $attachment))
            ->assertForbidden();
    }
}
