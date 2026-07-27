<?php

namespace App\Http\Controllers\Bid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bid\StoreBidCompanyRequest;
use App\Http\Requests\Bid\UpdateBidCompanyRequest;
use App\Models\BidBusinessLine;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentCategory;
use App\Models\BidDocumentType;
use App\Services\Bid\BidDashboardService;
use Illuminate\Http\Request;

/** Empresas licitantes e o cofre de documentos de cada uma (ver specs/21 §9.2 e §9.3). */
class BidCompanyController extends Controller
{
    public function index(Request $request, BidDashboardService $dashboard)
    {
        $this->authorize('viewAny', BidCompany::class);

        $companies = BidCompany::query()
            ->with('businessLines')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($sub) => $sub
                ->where('corporate_name', 'like', "%{$s}%")
                ->orWhere('trade_name', 'like', "%{$s}%")
                ->orWhere('cnpj', 'like', '%'.preg_replace('/\D/', '', $s).'%')))
            ->when($request->size, fn ($q, $size) => $q->where('size', $size))
            ->when($request->business_line, fn ($q, $line) => $q->whereHas(
                'businessLines',
                fn ($sub) => $sub->whereKey($line)
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('corporate_name')
            ->paginate(15)
            ->withQueryString();

        // Saúde documental indexada por empresa — a lista mostra a mesma barra do painel.
        $health = $dashboard->companiesHealth()->keyBy(fn ($row) => $row['company']->id);

        return view('licitacoes.empresas.index', [
            'companies' => $companies,
            'health' => $health,
            'lines' => BidBusinessLine::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', BidCompany::class);

        return view('licitacoes.empresas.create', [
            'company' => new BidCompany(['size' => 'demais', 'active' => true, 'color' => '#0a0a0a']),
            'lines' => BidBusinessLine::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreBidCompanyRequest $request)
    {
        $data = $request->validated();
        $company = BidCompany::create($data);
        $company->businessLines()->sync($data['business_lines'] ?? []);

        BidDashboardService::forget();

        return redirect()
            ->route('bid.companies.show', $company)
            ->with('success', 'Empresa cadastrada. Agora envie os documentos de habilitação.');
    }

    /** Detalhe da empresa: abas de status + filtro de categoria, ambos server-side. */
    public function show(Request $request, BidCompany $company)
    {
        $this->authorize('view', $company);

        $documents = $company->documents()
            ->current()
            ->with(['category', 'type'])
            ->when($request->status, fn ($q, $status) => $q->status($status))
            ->when($request->category, fn ($q, $category) => $q->where('bid_document_category_id', $category))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByRaw('CASE WHEN no_expiry = 1 THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->paginate(20)
            ->withQueryString();

        return view('licitacoes.empresas.show', [
            'company' => $company->load('businessLines'),
            'documents' => $documents,
            'counters' => $this->statusCounters($company),
            'categories' => BidDocumentCategory::query()->active()->ordered()->get(),
            'types' => BidDocumentType::query()->active()->ordered()->get(),
            // Aba "Histórico": versões já substituídas, insumo do relatório de conformidade.
            'superseded' => $company->documents()
                ->withTrashed()
                ->whereNotNull('superseded_at')
                ->with(['category', 'uploader'])
                ->orderByDesc('superseded_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function edit(BidCompany $company)
    {
        $this->authorize('update', $company);

        return view('licitacoes.empresas.edit', [
            'company' => $company->load('businessLines'),
            'lines' => BidBusinessLine::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateBidCompanyRequest $request, BidCompany $company)
    {
        $data = $request->validated();
        $company->update($data);
        $company->businessLines()->sync($data['business_lines'] ?? []);

        BidDashboardService::forget();

        return redirect()->route('bid.companies.show', $company)->with('success', 'Empresa atualizada.');
    }

    public function destroy(BidCompany $company)
    {
        $this->authorize('delete', $company);

        if ($company->evaluations()->exists()) {
            return back()->with('error', 'Esta empresa já participou de análises de edital e não pode ser excluída. Inative-a no cadastro.');
        }

        $company->delete();
        BidDashboardService::forget();

        return redirect()->route('bid.companies.index')->with('success', 'Empresa excluída.');
    }

    /** Contadores das abas de status do acervo desta empresa (uma query). */
    private function statusCounters(BidCompany $company): array
    {
        $row = $company->documents()
            ->current()
            ->selectRaw(BidDocument::countExpression('total').' as total')
            ->selectRaw(BidDocument::countExpression('valido').' as validos')
            ->selectRaw(BidDocument::countExpression('vencendo').' as vencendo')
            ->selectRaw(BidDocument::countExpression('vencido').' as vencidos')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'valido' => (int) ($row->validos ?? 0),
            'vencendo' => (int) ($row->vencendo ?? 0),
            'vencido' => (int) ($row->vencidos ?? 0),
        ];
    }
}
