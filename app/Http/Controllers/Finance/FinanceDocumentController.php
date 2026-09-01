<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateFinanceDocument;
use App\Actions\Finance\DeriveCostItemStatus;
use App\Domain\Enums\FinanceDocumentKind;
use App\Models\CardAttachment;
use App\Models\FinanceCostItem;
use App\Models\FinanceDocument;
use App\Support\FinancePresenter;
use App\Support\ServesStoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * O painel de documentos (o bloco CONTROLE da planilha) — specs/23 §4.5 e §8.4.
 *
 * O documento vindo do card é uma REFERÊNCIA ao anexo: nada é copiado, e é isso que elimina o
 * upload em dois lugares.
 */
class FinanceDocumentController extends FinanceController
{
    use ServesStoredFile;

    /** Conteúdo do drawer: documentos da linha + anexos do card ainda não classificados. */
    public function index(FinanceCostItem $item)
    {
        $this->authorize('view', $item->loadMissing('sheet')->sheet);

        $documents = $item->documents()->with(['attachment:id,card_id,original_name,size', 'uploader:id,name'])
            ->orderBy('kind')->orderByDesc('id')->get();

        $linkedIds = $documents->pluck('card_attachment_id')->filter()->all();

        // Anexos do card que ainda não viraram documento — inclui `geral` e `minuta`, que não têm
        // mapa automático e só entram quando alguém classifica (specs/23 §6.4).
        $pending = $item->card_id
            ? CardAttachment::where('card_id', $item->card_id)
                ->whereNotIn('id', $linkedIds)
                ->get(['id', 'kind', 'original_name', 'size'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'size' => $a->size,
                    'attachment_kind' => $a->kind->value,
                    'attachment_label' => $a->kind->label(),
                    'suggested_kind' => FinanceDocumentKind::fromAttachmentKind($a->kind)?->value,
                ])->values()
            : collect();

        return response()->json([
            'documents' => $documents->map(fn ($d) => FinancePresenter::document($d))->values(),
            'pending_attachments' => $pending,
            'kinds' => collect(FinanceDocumentKind::cases())->map(fn ($k) => [
                'value' => $k->value, 'label' => $k->label(), 'icon' => $k->icon(),
            ])->values(),
            'item' => FinancePresenter::costItem($item),
        ]);
    }

    /** Upload direto no financeiro (guia, boleto, taxa que nunca passou por card). */
    public function store(Request $request, FinanceCostItem $item, CreateFinanceDocument $action, DeriveCostItemStatus $derive)
    {
        $this->authorizeWrite($item->loadMissing('sheet')->sheet);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'kind' => ['required', Rule::in(array_column(FinanceDocumentKind::cases(), 'value'))],
        ]);

        $document = $action->fromUpload($item, $request->file('file'), FinanceDocumentKind::from($data['kind']), $request->user());
        $derive->execute($item);

        return response()->json([
            'document' => FinancePresenter::document($document),
            'item' => FinancePresenter::costItem($item->refresh()),
        ], 201);
    }

    /** Classifica um anexo do card como documento de controle (sem cópia de arquivo). */
    public function attach(Request $request, FinanceCostItem $item, CreateFinanceDocument $action, DeriveCostItemStatus $derive)
    {
        $this->authorizeWrite($item->loadMissing('sheet')->sheet);

        $data = $request->validate([
            'card_attachment_id' => ['required', 'exists:card_attachments,id'],
            'kind' => ['required', Rule::in(array_column(FinanceDocumentKind::cases(), 'value'))],
        ]);

        $attachment = CardAttachment::findOrFail($data['card_attachment_id']);

        // Só anexos do card que originou a linha — evita "emprestar" comprovante de outro card.
        abort_unless($item->card_id && $attachment->card_id === $item->card_id, 403);

        $document = $action->fromAttachment($item, $attachment, FinanceDocumentKind::from($data['kind']), $request->user());
        $derive->execute($item);

        return response()->json([
            'document' => FinancePresenter::document($document),
            'item' => FinancePresenter::costItem($item->refresh()),
        ], 201);
    }

    /** Abre o arquivo (PDF/imagem inline, o resto baixa) — mesma regra segura dos anexos do card. */
    public function show(FinanceDocument $document)
    {
        $document->loadMissing('costItem.sheet');
        $this->authorize('view', $document->costItem->sheet);

        $path = $document->storagePath();
        abort_if($path === null, 404);

        return $this->serveStoredFile($path, $document->displayName());
    }

    public function destroy(FinanceDocument $document, DeriveCostItemStatus $derive)
    {
        $item = $document->loadMissing('costItem.sheet')->costItem;
        $this->authorizeWrite($item->sheet);

        // Só apaga arquivo de upload direto. Documento que referencia anexo do card some daqui,
        // mas o arquivo continua no card — ele não é nosso.
        if (! $document->fromCard() && $document->path) {
            Storage::disk('local')->delete($document->path);
        }

        $document->delete();
        $derive->execute($item);

        return response()->json(['ok' => true, 'item' => FinancePresenter::costItem($item->refresh())]);
    }
}
