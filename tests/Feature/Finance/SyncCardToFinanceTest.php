<?php

namespace Tests\Feature\Finance;

use App\Actions\Cards\TransferCard;
use App\Actions\Finance\SyncCardToFinance;
use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\CardNegociado;
use App\Domain\Enums\FinanceCostStatus;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\Card;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use Illuminate\Validation\ValidationException;

/**
 * A ponte Kanban -> Financeiro (specs/23 §6): é o que acaba com o "subir no card e subir de novo
 * na planilha". Idempotência e o mapa de tipos são o que mais importa aqui.
 */
class SyncCardToFinanceTest extends FinanceTestCase
{
    private function attach(Card $card, AttachmentKind $kind, string $name = 'arquivo.pdf'): void
    {
        $card->attachments()->create([
            'kind' => $kind->value,
            'original_name' => $name,
            'path' => "card-attachments/{$card->id}/".uniqid().'.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ]);
    }

    public function test_cria_linha_de_custo_a_partir_do_card_e_vincula_os_anexos(): void
    {
        $event = $this->event();
        $board = $this->board();
        $card = $this->card($board, $event, ['estimated_value' => 2500, 'actual_value' => 2300]);

        $this->attach($card, AttachmentKind::Orcamento, 'orcamento.pdf');
        $this->attach($card, AttachmentKind::NotaFiscal, 'nf.pdf');

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $this->assertSame($card->id, $item->card_id);
        $this->assertSame('Locação de som', $item->description);
        $this->assertEquals(2500, (float) $item->unit_estimated_1);
        $this->assertEquals(2300, (float) $item->unit_actual);
        $this->assertSame(2, $item->documents()->count());

        // Documento é REFERÊNCIA ao anexo do card: nada é copiado.
        $this->assertNull(FinanceDocument::first()->path);
        $this->assertNotNull(FinanceDocument::first()->card_attachment_id);
    }

    public function test_reenviar_o_mesmo_card_nao_duplica_linha_nem_documento(): void
    {
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $this->attach($card, AttachmentKind::Comprovante);

        $action = app(SyncCardToFinance::class);
        $action->execute($card->fresh(), $this->user());
        $action->execute($card->fresh(), $this->user());

        $this->assertSame(1, FinanceCostItem::count());
        $this->assertSame(1, FinanceDocument::count());
    }

    public function test_card_sem_evento_e_recusado(): void
    {
        $card = $this->card($this->board(), $this->event());
        $card->update(['event_id' => null]);

        $this->expectException(ValidationException::class);

        app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());
    }

    public function test_anexo_geral_e_minuta_nao_viram_documento_automaticamente(): void
    {
        $card = $this->card($this->board(), $this->event());
        $this->attach($card, AttachmentKind::Geral, 'foto.jpg');
        $this->attach($card, AttachmentKind::Minuta, 'minuta.pdf');

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        // Minuta é a PROPOSTA do fornecedor, não o contrato assinado: promovê-la marcaria o
        // controle "CONTRATO" antes de existir contrato.
        $this->assertSame(0, $item->documents()->count());
    }

    public function test_anexo_novo_em_card_ja_vinculado_vira_documento_pelo_observer(): void
    {
        $card = $this->card($this->board(), $this->event());
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $this->assertSame(0, $item->documents()->count());

        $this->attach($card, AttachmentKind::Contrato, 'contrato.pdf');

        $this->assertSame(1, $item->refresh()->documents()->count());
        $this->assertSame(FinanceDocumentKind::Contrato, $item->documents()->sole()->kind);
    }

    public function test_status_e_derivado_dos_documentos_presentes(): void
    {
        $card = $this->card($this->board(), $this->event());
        $this->attach($card, AttachmentKind::Orcamento);

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());
        $this->assertSame(FinanceCostStatus::AguardandoContrato, $item->status);

        $this->attach($card, AttachmentKind::Contrato);
        $this->assertSame(FinanceCostStatus::ContratoFaltaNota, $item->refresh()->status);

        $this->attach($card, AttachmentKind::NotaFiscal);
        $this->assertSame(FinanceCostStatus::ContratoNotaOk, $item->refresh()->status);
    }

    public function test_status_editado_a_mao_para_de_ser_derivado(): void
    {
        $card = $this->card($this->board(), $this->event());
        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $user = $this->user();
        $this->actingAs($user)
            ->putJson(route('finance.costs.update', $item), [
                'description' => $item->description,
                'status' => FinanceCostStatus::NaoAplicado->value,
            ])->assertOk();

        $this->attach($card, AttachmentKind::Contrato);

        $this->assertFalse($item->refresh()->status_auto);
        $this->assertSame(FinanceCostStatus::NaoAplicado, $item->status);
    }

    public function test_valor_negociado_do_card_tem_precedencia_sobre_actual_value(): void
    {
        $card = $this->card($this->board(), $this->event(), [
            'actual_value' => 1000,
            'valor_com_nota' => 1200,
            'valor_sem_nota' => 900,
            'negociado' => CardNegociado::ComNota->value,
        ]);

        $item = app(SyncCardToFinance::class)->execute($card->fresh(), $this->user());

        $this->assertEquals(1200, (float) $item->unit_actual);
    }

    public function test_card_transferido_para_quadro_que_alimenta_o_financeiro_sincroniza_sozinho(): void
    {
        $event = $this->event();
        $origem = $this->board('Orçamentos');
        $financeiro = $this->board('Financeiro', feedsFinance: true);
        $card = $this->card($origem, $event, ['estimated_value' => 800]);
        $this->attach($card, AttachmentKind::NotaFiscal);

        app(TransferCard::class)->execute($card, $financeiro, null, $this->user());

        $item = FinanceCostItem::sole();
        $this->assertSame($card->id, $item->card_id);
        $this->assertSame(1, $item->documents()->count());
    }

    public function test_falha_da_sincronia_automatica_nao_derruba_a_movimentacao_do_card(): void
    {
        $event = $this->event();
        $origem = $this->board('Orçamentos');
        $financeiro = $this->board('Financeiro', feedsFinance: true);
        $card = $this->card($origem, $event);

        // Prestação de contas fechada: a sincronia não acontece, mas o card move normalmente.
        $this->sheet($event)->update(['status' => 'fechado']);

        app(TransferCard::class)->execute($card, $financeiro, null, $this->user());

        $this->assertSame($financeiro->id, $card->refresh()->board_id);
        $this->assertSame(0, FinanceCostItem::count());
    }
}
