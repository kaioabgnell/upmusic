<?php

namespace Tests\Feature\Supplier;

use App\Domain\Enums\MovementType;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\CardSupplierForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regressão do avanço automático de coluna na minuta do fornecedor (specs/19) depois que ele saiu
 * de um método privado de `ProcessSupplierMinuta` para a Action compartilhada
 * `AdvanceCardToNextColumn` (mesma usada pela Solicitação de Nota). Não havia teste algum disto
 * antes — a spec só registra "validado por HTTP real" — então esta classe é o que passa a travar
 * o comportamento, não apenas o refactor em si.
 */
class SupplierMinutaColumnAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private function form(Card $card): CardSupplierForm
    {
        return CardSupplierForm::create(['card_id' => $card->id, 'token' => 'tok-minuta-'.$card->id, 'active' => true]);
    }

    public function test_minuta_recebida_avanca_o_card_para_a_proxima_coluna(): void
    {
        Storage::fake('local');
        $board = Board::create(['name' => 'Produção', 'allows_supplier_form' => true]);
        $pendente = BoardColumn::create(['board_id' => $board->id, 'name' => 'Minuta pendente', 'position' => 1]);
        $recebida = BoardColumn::create(['board_id' => $board->id, 'name' => 'Minuta recebida', 'position' => 2]);
        $card = Card::create(['board_id' => $board->id, 'board_column_id' => $pendente->id, 'title' => 'Som']);
        $form = $this->form($card);

        $this->post(route('supplier.form.submit', $form->token), [
            'minuta' => UploadedFile::fake()->create('minuta.pdf', 12, 'application/pdf'),
        ])->assertRedirect(route('supplier.form.success', $form->token));

        $card->refresh();
        $this->assertSame($recebida->id, $card->board_column_id);

        $movimento = $card->movements()->sole();
        $this->assertSame(MovementType::MinutaRecebida, $movimento->type);
        $this->assertNull($movimento->user_id);
    }

    public function test_minuta_na_ultima_coluna_nao_quebra(): void
    {
        Storage::fake('local');
        $board = Board::create(['name' => 'Produção', 'allows_supplier_form' => true]);
        $unica = BoardColumn::create(['board_id' => $board->id, 'name' => 'Única', 'position' => 1]);
        $card = Card::create(['board_id' => $board->id, 'board_column_id' => $unica->id, 'title' => 'Som']);
        $form = $this->form($card);

        $this->post(route('supplier.form.submit', $form->token), [
            'minuta' => UploadedFile::fake()->create('minuta.pdf', 12, 'application/pdf'),
        ])->assertRedirect(route('supplier.form.success', $form->token));

        $this->assertSame($unica->id, $card->refresh()->board_column_id);
    }
}
