<?php

namespace App\Http\Controllers\Bid;

use App\Actions\Bid\StoreBidDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bid\ReadBidDocumentRequest;
use App\Http\Requests\Bid\StoreBidDocumentRequest;
use App\Http\Requests\Bid\UpdateBidDocumentRequest;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Services\Bid\BidDashboardService;
use App\Services\Bid\DocumentReader;
use App\Services\Bid\GeminiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Cofre de documentos: upload, leitura assistida, renovação e histórico (ver specs/21 §9.3/§9.4). */
class BidDocumentController extends Controller
{
    public function store(StoreBidDocumentRequest $request, BidCompany $company, StoreBidDocument $action)
    {
        $action->execute($company, $request->validated(), $request->file('arquivo'));
        BidDashboardService::forget();

        return redirect()
            ->route('bid.companies.show', $company)
            ->with('success', 'Documento adicionado ao acervo.');
    }

    public function update(UpdateBidDocumentRequest $request, BidDocument $document)
    {
        $data = $request->validated();

        $document->update([
            'name' => $data['name'],
            'bid_document_category_id' => $data['bid_document_category_id'],
            'bid_document_type_id' => $data['bid_document_type_id'] ?? null,
            'control_code' => $data['control_code'] ?? null,
            'issuer' => $data['issuer'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => ($data['no_expiry'] ?? false) ? null : ($data['expires_at'] ?? null),
            'no_expiry' => (bool) ($data['no_expiry'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        BidDashboardService::forget();

        return redirect()
            ->route('bid.companies.show', $document->bid_company_id)
            ->with('success', 'Documento atualizado.');
    }

    /**
     * Renovação: novo arquivo entra como versão vigente e o anterior vai para o histórico
     * (ver specs/21 §10.2). É o caminho do botão "Renovar" dos alertas do painel.
     */
    public function renew(StoreBidDocumentRequest $request, BidDocument $document, StoreBidDocument $action)
    {
        $this->authorize('update', $document);

        $action->execute($document->company, $request->validated(), $request->file('arquivo'), $document);
        BidDashboardService::forget();

        return redirect()
            ->route('bid.companies.show', $document->bid_company_id)
            ->with('success', 'Documento renovado — a versão anterior ficou no histórico.');
    }

    public function destroy(BidDocument $document)
    {
        $this->authorize('delete', $document);

        $companyId = $document->bid_company_id;

        // Remove o arquivo antes do registro; falha de limpeza não interrompe (§12).
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        BidDashboardService::forget();

        return redirect()
            ->route('bid.companies.show', $companyId)
            ->with('success', 'Documento excluído.');
    }

    /** Arquivo servido por rota autenticada — nada em `public/` (ver specs/21 §12). */
    public function file(Request $request, BidDocument $document)
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return $request->boolean('download')
            ? Storage::disk('local')->download($document->file_path, $document->original_name)
            : Storage::disk('local')->response(
                $document->file_path,
                $document->original_name,
                ['Content-Type' => $document->mime_type, 'Content-Disposition' => 'inline; filename="'.$document->original_name.'"']
            );
    }

    /** Versões anteriores deste documento (cadeia de `supersedes_id`). */
    public function history(BidDocument $document)
    {
        $this->authorize('view', $document);

        $versions = collect();
        $current = $document;

        while ($previous = BidDocument::withTrashed()->with('uploader')->find($current->supersedes_id)) {
            $versions->push($previous);
            $current = $previous;
        }

        return view('licitacoes.empresas.historico', [
            'document' => $document->load(['company', 'category', 'type', 'uploader']),
            'versions' => $versions,
        ]);
    }

    /**
     * Leitura assistida (Fase B): devolve sugestões para o formulário. Não persiste nada e, se a
     * IA falhar, responde com erro tratado para o formulário abrir em branco (§9.4).
     */
    public function read(ReadBidDocumentRequest $request, DocumentReader $reader)
    {
        $company = $request->filled('bid_company_id')
            ? BidCompany::find($request->input('bid_company_id'))
            : null;

        try {
            return response()->json([
                'ok' => true,
                'suggestion' => $reader->read($request->file('arquivo'), $company),
            ]);
        } catch (GeminiException $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $e->userMessage()], 422);
        }
    }
}
