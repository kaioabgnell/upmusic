<?php

namespace Tests\Unit;

use App\Domain\Enums\FinanceDocumentKind;
use App\Models\Event;
use App\Models\FinanceSheet;
use App\Support\FinancePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chips do bloco CONTROLE na grade de Custos (specs/23 §4.5, §8.4). "Recibo" foi adicionado como
 * documento não-prova (como Geral/Minuta): tem ícone próprio e aparece na grade só quando existe,
 * mas fica fora de `proofKinds()` para não mexer no layout de seis colunas do arquivo exportado.
 */
class FinancePresenterTest extends TestCase
{
    use RefreshDatabase;

    private function costItem(): \App\Models\FinanceCostItem
    {
        $event = Event::create([
            'name' => 'Festa Junina', 'start_date' => '2026-06-20', 'end_date' => '2026-06-21', 'active' => true,
        ]);
        $sheet = FinanceSheet::create(['event_id' => $event->id]);

        return $sheet->costItems()->create(['description' => 'Diária de som'])->refresh();
    }

    public function test_recibo_tem_icone_proprio_e_nao_e_marcado_como_prova(): void
    {
        $item = $this->costItem();

        $chips = collect(FinancePresenter::costItem($item)['documents'])->keyBy('kind');

        $this->assertSame('fa-money-check-dollar', $chips['recibo']['icon']);
        $this->assertSame('Recibo', $chips['recibo']['label']);
        $this->assertFalse($chips['recibo']['proof'], 'Recibo não é uma das seis colunas do arquivo modelo — ver FinanceDocumentKind::proofKinds().');
        $this->assertSame(0, $chips['recibo']['count']);
    }

    public function test_chip_do_recibo_conta_o_documento_quando_anexado(): void
    {
        $item = $this->costItem();
        $item->documents()->create(['kind' => FinanceDocumentKind::Recibo->value, 'path' => 'x', 'original_name' => 'recibo.pdf']);

        $chips = collect(FinancePresenter::costItem($item->refresh())['documents'])->keyBy('kind');

        $this->assertSame(1, $chips['recibo']['count']);
    }

    public function test_seis_documentos_de_prova_continuam_os_mesmos_apos_adicionar_recibo(): void
    {
        // Guarda de regressão: o export XLSX usa proofKinds() para as seis colunas fixas do
        // arquivo modelo (V-AA). Recibo não pode entrar aí.
        $this->assertCount(6, FinanceDocumentKind::proofKinds());
        $this->assertNotContains(FinanceDocumentKind::Recibo, FinanceDocumentKind::proofKinds());
    }
}
