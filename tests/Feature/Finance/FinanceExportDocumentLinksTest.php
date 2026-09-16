<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\CreateFinanceDocument;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\FinanceCostItem;
use App\Services\Finance\FinanceExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Links dos comprovantes no XLSX exportado (specs/23 §12).
 *
 * O bloco CONTROLE era um "X": dizia que o comprovante existe, mas quem recebe a planilha tinha de
 * entrar no sistema e procurar o arquivo. Agora cada coluna com arquivo leva até ele.
 */
class FinanceExportDocumentLinksTest extends FinanceTestCase
{
    /** Coluna (1-indexed) de um tipo de documento dentro do bloco CONTROLE da aba CUSTOS. */
    private function controlColumn(FinanceDocumentKind $kind): int
    {
        $sources = \App\Models\FinancePaymentSource::ordered()->count();
        $offset = array_search($kind, FinanceDocumentKind::proofKinds(), true);

        return 14 + $sources + 2 + 1 + $offset;
    }

    /** `sheet()` cria um evento novo a cada chamada — guardar a planilha mantém item e export juntos. */
    private ?\App\Models\FinanceSheet $sheet = null;

    private function sheetUnderTest(): \App\Models\FinanceSheet
    {
        return $this->sheet ??= $this->sheet();
    }

    private function costItem(): FinanceCostItem
    {
        return $this->sheetUnderTest()->costItems()->create(['description' => 'Gerador 260KVA']);
    }

    private function upload(FinanceCostItem $item, string $name, FinanceDocumentKind $kind)
    {
        return app(CreateFinanceDocument::class)->fromUpload(
            $item,
            UploadedFile::fake()->create($name, 12, 'application/pdf'),
            $kind,
            $this->user(),
        );
    }

    private function costsSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = app(FinanceExportService::class)->toXlsx($this->sheetUnderTest()->refresh());

        return IOFactory::load($path)->getSheetByName('CUSTOS');
    }

    public function test_coluna_de_controle_leva_ao_arquivo(): void
    {
        Storage::fake('local');
        $item = $this->costItem();
        $document = $this->upload($item, 'nota-fiscal.pdf', FinanceDocumentKind::NotaFiscal);

        $cell = $this->costsSheet()->getCell([$this->controlColumn(FinanceDocumentKind::NotaFiscal), 8]);

        $this->assertSame('nota-fiscal.pdf', $cell->getValue());
        $this->assertSame(route('finance.documents.show', $document), $cell->getHyperlink()->getUrl());
    }

    /** Uma célula só comporta um link: os demais arquivos do mesmo tipo viram um contador. */
    public function test_varios_arquivos_do_mesmo_tipo_linkam_o_primeiro_e_contam_o_resto(): void
    {
        Storage::fake('local');
        $item = $this->costItem();
        $primeiro = $this->upload($item, 'parcela-1.pdf', FinanceDocumentKind::Comprovante);
        $this->upload($item, 'parcela-2.pdf', FinanceDocumentKind::Comprovante);
        $this->upload($item, 'parcela-3.pdf', FinanceDocumentKind::Comprovante);

        $cell = $this->costsSheet()->getCell([$this->controlColumn(FinanceDocumentKind::Comprovante), 8]);

        $this->assertSame('parcela-1.pdf (+2)', $cell->getValue());
        $this->assertSame(route('finance.documents.show', $primeiro), $cell->getHyperlink()->getUrl());
    }

    /**
     * Recibo (e Geral/Minuta) não faz parte das seis colunas do arquivo modelo, mas existe no
     * sistema: sem isso, quem anexava um recibo não via nada na planilha exportada.
     */
    public function test_tipo_fora_do_modelo_vira_coluna_com_link_quando_tem_arquivo(): void
    {
        Storage::fake('local');
        $item = $this->costItem();
        $recibo = $this->upload($item, 'recibo-pix.pdf', FinanceDocumentKind::Recibo);

        $sheet = $this->costsSheet();
        // Entra DEPOIS das seis do modelo, para não deslocar o que o import lê por posição fixa.
        $column = 14 + \App\Models\FinancePaymentSource::ordered()->count() + 2 + 1 + 6;

        $this->assertSame('RECIBO', $sheet->getCell([$column, 7])->getValue());
        $this->assertSame('recibo-pix.pdf', $sheet->getCell([$column, 8])->getValue());
        $this->assertSame(
            route('finance.documents.show', $recibo),
            $sheet->getCell([$column, 8])->getHyperlink()->getUrl(),
        );
    }

    /** As seis colunas do modelo seguem nas mesmas posições — o import as lê por posição fixa. */
    public function test_colunas_do_modelo_nao_se_deslocam_com_os_tipos_extras(): void
    {
        Storage::fake('local');
        $item = $this->costItem();
        $this->upload($item, 'recibo-pix.pdf', FinanceDocumentKind::Recibo);
        $this->upload($item, 'orcamento.pdf', FinanceDocumentKind::Orcamento);

        $sheet = $this->costsSheet();

        foreach (FinanceDocumentKind::proofKinds() as $offset => $kind) {
            $this->assertSame(
                mb_strtoupper($kind->label()),
                $sheet->getCell([$this->controlColumn($kind), 7])->getValue(),
                "A coluna de {$kind->label()} saiu do lugar previsto pelo import.",
            );
        }

        $this->assertSame('orcamento.pdf', $sheet->getCell([$this->controlColumn(FinanceDocumentKind::Orcamento), 8])->getValue());
    }

    /** Tipo fora do modelo e sem arquivo não vira coluna: seria só ruído na planilha. */
    public function test_tipo_fora_do_modelo_sem_arquivo_nao_vira_coluna(): void
    {
        Storage::fake('local');
        $item = $this->costItem();
        $this->upload($item, 'orcamento.pdf', FinanceDocumentKind::Orcamento);

        $sheet = $this->costsSheet();
        $column = 14 + \App\Models\FinancePaymentSource::ordered()->count() + 2 + 1 + 6;

        $this->assertNull($sheet->getCell([$column, 7])->getValue());
    }

    public function test_tipo_sem_arquivo_continua_vazio_e_sem_link(): void
    {
        Storage::fake('local');
        $item = $this->costItem();
        $this->upload($item, 'nota-fiscal.pdf', FinanceDocumentKind::NotaFiscal);

        $cell = $this->costsSheet()->getCell([$this->controlColumn(FinanceDocumentKind::Boleto), 8]);

        // Célula sem conteúdo volta como null na leitura do arquivo.
        $this->assertNull($cell->getValue());
        $this->assertSame('', $cell->getHyperlink()->getUrl());
    }

    /**
     * O documento que veio de um anexo do card guarda o nome no anexo, não em si — se a exportação
     * não trouxer essa relação junto, o guard de N+1 derruba o download inteiro fora de produção.
     */
    public function test_documento_vindo_do_card_exporta_o_nome_do_anexo(): void
    {
        Storage::fake('local');
        $event = $this->event();
        $card = $this->card($this->board(), $event);
        $attachment = $card->attachments()->create([
            'kind' => \App\Domain\Enums\AttachmentKind::Contrato->value,
            'original_name' => 'contrato-assinado.pdf',
            'path' => 'card-attachments/1/contrato.pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
        ]);

        $item = $this->sheet($event)->costItems()->create(['description' => 'Locação de som']);
        $document = app(CreateFinanceDocument::class)
            ->fromAttachment($item, $attachment, FinanceDocumentKind::Contrato, $this->user());

        $path = app(FinanceExportService::class)->toXlsx($this->sheet($event)->refresh());
        $cell = IOFactory::load($path)->getSheetByName('CUSTOS')
            ->getCell([$this->controlColumn(FinanceDocumentKind::Contrato), 8]);

        $this->assertSame('contrato-assinado.pdf', $cell->getValue());
        $this->assertSame(route('finance.documents.show', $document), $cell->getHyperlink()->getUrl());
    }
}
