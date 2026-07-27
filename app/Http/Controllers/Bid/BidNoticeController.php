<?php

namespace App\Http\Controllers\Bid;

use App\Actions\Bid\AnalyzeNotice;
use App\Actions\Bid\EvaluateNotice;
use App\Domain\Enums\BidMatchStatus;
use App\Domain\Enums\BidNoticeSource;
use App\Domain\Enums\BidNoticeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bid\StoreBidNoticeRequest;
use App\Http\Requests\Bid\UpdateBidNoticeRequest;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidNotice;
use App\Models\BidRequirementMatch;
use App\Services\Bid\GeminiException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Análise de edital: envio, resultado, matriz e plano de regularização (ver specs/21 §9.5/§9.6). */
class BidNoticeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BidNotice::class);

        $notices = BidNotice::query()
            ->with(['evaluations' => fn ($q) => $q->where('rank', 1)->with('company')])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$s}%")
                ->orWhere('agency', 'like', "%{$s}%")
                ->orWhere('number', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('licitacoes.editais.index', compact('notices'));
    }

    public function create()
    {
        $this->authorize('create', BidNotice::class);

        return view('licitacoes.editais.create', [
            'companies' => BidCompany::query()->active()->orderBy('corporate_name')->get(),
        ]);
    }

    /**
     * Cria o registro e roda a análise na mesma requisição (§5.2 — sem fila, sem worker).
     * Responde JSON porque a tela chama por `fetch` e mostra o progresso com SweetAlert2.
     */
    public function store(StoreBidNoticeRequest $request, AnalyzeNotice $analyze)
    {
        $data = $request->validated();
        $file = $request->file('arquivo');

        $notice = BidNotice::create([
            'title' => $data['title'] ?? ($file?->getClientOriginalName() ?: 'Edital colado em '.now()->format('d/m/Y H:i')),
            'status' => BidNoticeStatus::Rascunho,
            'source' => $this->sourceFor($file),
            'file_path' => $file ? $this->storeFile($file) : null,
            'original_name' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getMimeType(),
            'file_size' => $file?->getSize(),
            'raw_text' => $data['raw_text'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $companies = $this->selectedCompanies($data['companies'] ?? []);

        try {
            $analyze->execute($notice, $companies);
        } catch (GeminiException $e) {
            report($e);

            // O registro fica em `erro` e reprocessável — nada de trabalho perdido.
            return response()->json([
                'ok' => false,
                'message' => $e->userMessage(),
                'redirect' => route('bid.notices.show', $notice),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'redirect' => route('bid.notices.show', $notice),
        ]);
    }

    /** Resultado da análise: ranking + matriz de conformidade. */
    public function show(BidNotice $notice, EvaluateNotice $evaluate)
    {
        $this->authorize('view', $notice);

        // Reavaliação é determinística e gratuita (sem IA): quando o acervo mudou depois da última
        // avaliação, recalcula na abertura para a tela nunca mostrar situação vencida (§10.4).
        $recalculated = false;

        if ($notice->status === BidNoticeStatus::Analisado && $notice->acervoChangedAfterEvaluation()) {
            $evaluate->execute($notice);
            $recalculated = true;
        }

        $notice->load([
            'requirements.type', 'requirements.category',
            'evaluations' => fn ($q) => $q->orderBy('rank')->with('company'),
        ]);

        $matches = BidRequirementMatch::query()
            ->whereIn('bid_notice_requirement_id', $notice->requirements->pluck('id'))
            ->with('document:id,name,expires_at,no_expiry,control_code,original_name')
            ->get();

        return view('licitacoes.editais.show', [
            'notice' => $notice,
            'matrix' => $matches->groupBy('bid_notice_requirement_id')
                ->map(fn (Collection $rows) => $rows->keyBy('bid_company_id')),
            'lowConfidence' => $matches
                ->where('confidence', 'baixa')
                ->whereIn('status', [BidMatchStatus::Atendido, BidMatchStatus::Vencendo])
                ->groupBy('bid_company_id')
                ->map->count(),
            'documentsByCompany' => $this->documentsByCompany($notice),
            'recalculated' => $recalculated,
        ]);
    }

    public function update(UpdateBidNoticeRequest $request, BidNotice $notice, EvaluateNotice $evaluate)
    {
        $notice->update($request->validated());

        // Valor estimado alimenta requisitos percentuais: mexer nele exige recálculo.
        $evaluate->execute($notice);

        return back()->with('success', 'Dados do edital atualizados e aptidão recalculada.');
    }

    /** Nova leitura pela IA — também é o caminho de recuperação de análise interrompida (§5.2). */
    public function reprocess(BidNotice $notice, AnalyzeNotice $analyze)
    {
        $this->authorize('update', $notice);

        try {
            $analyze->execute($notice);
        } catch (GeminiException $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $e->userMessage()], 422);
        }

        return response()->json(['ok' => true, 'redirect' => route('bid.notices.show', $notice)]);
    }

    /** Recálculo determinístico, sem IA e sem custo. */
    public function reevaluate(BidNotice $notice, EvaluateNotice $evaluate)
    {
        $this->authorize('update', $notice);

        $evaluate->execute($notice);

        return back()->with('success', 'Aptidão recalculada com o acervo atual.');
    }

    /** Plano de regularização de uma empresa para este edital (§9.6). */
    public function plan(BidNotice $notice, BidCompany $company)
    {
        $this->authorize('view', $notice);

        $matches = BidRequirementMatch::query()
            ->where('bid_company_id', $company->id)
            ->whereIn('bid_notice_requirement_id', $notice->requirements()->pluck('id'))
            ->with(['requirement.type', 'document'])
            ->get()
            ->filter(fn (BidRequirementMatch $match) => in_array($match->status, [
                BidMatchStatus::Ausente, BidMatchStatus::Vencido, BidMatchStatus::Vencendo, BidMatchStatus::Conferir,
            ], true))
            ->values();

        // Ordem de urgência: o que bloqueia primeiro, depois o que vence, por fim o que conferir.
        // Tupla + comparador explícito (sortBy com array de callables usa cada um como comparador).
        $urgency = fn (BidRequirementMatch $match): array => [
            match ($match->status) {
                BidMatchStatus::Ausente, BidMatchStatus::Vencido => 0,
                BidMatchStatus::Vencendo => 1,
                default => 2,
            },
            $match->requirement->mandatory ? 0 : 1,
            $match->requirement->sort_order,
        ];

        $matches = $matches
            ->sort(fn (BidRequirementMatch $a, BidRequirementMatch $b) => $urgency($a) <=> $urgency($b))
            ->values();

        return view('licitacoes.editais.plano', [
            'notice' => $notice,
            'company' => $company,
            'items' => $matches,
            'evaluation' => $notice->evaluations()->where('bid_company_id', $company->id)->first(),
        ]);
    }

    /** Matriz de conformidade em CSV (`;`, UTF-8 com BOM) — padrão de exportação do projeto. */
    public function matrix(BidNotice $notice)
    {
        $this->authorize('view', $notice);

        $notice->load(['requirements', 'evaluations.company']);

        $matches = BidRequirementMatch::query()
            ->whereIn('bid_notice_requirement_id', $notice->requirements->pluck('id'))
            ->get()
            ->groupBy('bid_notice_requirement_id');

        $companies = $notice->evaluations->map->company;

        $lines = ["\u{FEFF}".implode(';', array_merge(
            ['Requisito', 'Natureza', 'Obrigatorio'],
            $companies->map(fn (BidCompany $c) => str_replace(';', ',', $c->corporate_name))->all()
        ))];

        foreach ($notice->requirements as $requirement) {
            $row = [
                str_replace(';', ',', $requirement->name),
                $requirement->kind->label(),
                $requirement->mandatory ? 'Sim' : 'Nao',
            ];

            foreach ($companies as $company) {
                $row[] = $matches->get($requirement->id)?->firstWhere('bid_company_id', $company->id)?->status->label() ?? '-';
            }

            $lines[] = implode(';', $row);
        }

        $filename = 'matriz-'.Str::slug($notice->title ?: 'edital').'.csv';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function destroy(BidNotice $notice)
    {
        $this->authorize('delete', $notice);

        if ($notice->file_path) {
            Storage::disk('local')->delete($notice->file_path);
        }

        $notice->delete();

        return redirect()->route('bid.notices.index')->with('success', 'Análise excluída.');
    }

    // Internos --------------------------------------------------------------

    private function sourceFor(?UploadedFile $file): BidNoticeSource
    {
        if (! $file) {
            return BidNoticeSource::Texto;
        }

        return $file->getMimeType() === 'application/pdf'
            ? BidNoticeSource::Pdf
            : BidNoticeSource::Imagem;
    }

    /** Mesmo cuidado do acervo: fora de `public/`, nome sanitizado (§12). */
    private function storeFile(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe = Str::of($base)->ascii()->replaceMatches('/[^a-zA-Z0-9._-]/', '_')->limit(60, '')->trim('_');

        return $file->storeAs(
            'licitacoes/editais',
            Str::random(8).'_'.($safe->isEmpty() ? 'edital' : $safe).'.'.$extension,
            'local'
        );
    }

    /** @param  array<int>  $ids */
    private function selectedCompanies(array $ids): ?Collection
    {
        if ($ids === []) {
            return null;
        }

        return BidCompany::query()->with('businessLines')->whereIn('id', $ids)->get();
    }

    /** Acervo vigente por empresa — alimenta o select de vínculo manual do painel do requisito. */
    private function documentsByCompany(BidNotice $notice): Collection
    {
        return BidDocument::query()
            ->current()
            ->whereIn('bid_company_id', $notice->evaluations->pluck('bid_company_id'))
            ->orderBy('name')
            ->get(['id', 'bid_company_id', 'name', 'expires_at', 'no_expiry'])
            ->groupBy('bid_company_id');
    }
}
