<?php

namespace App\Http\Controllers\Bid;

use App\Domain\Enums\BidVerdict;
use App\Http\Controllers\Controller;
use App\Models\BidCompany;
use App\Models\BidDocumentCategory;
use App\Models\BidNotice;
use App\Services\Bid\BidReportService;
use Illuminate\Http\Request;

/** Relatórios de dados passados (ver specs/21 §9.7). */
class BidReportController extends Controller
{
    public function index(Request $request, BidReportService $reports)
    {
        $this->authorize('viewAny', BidNotice::class);

        $filters = $this->filters($request);

        return view('licitacoes.relatorios.index', [
            'filters' => $filters,
            'history' => $reports->analysisHistory($filters),
            'aptitude' => $reports->aptitudeByCompany($filters),
            'blockers' => $reports->topBlockers($filters),
            'compliance' => $reports->documentCompliance($filters),
            'upcoming' => $reports->upcomingExpirations(90, $filters),
            'companies' => BidCompany::query()->orderBy('corporate_name')->get(),
            'categories' => BidDocumentCategory::query()->ordered()->get(),
            'verdicts' => BidVerdict::cases(),
        ]);
    }

    public function export(Request $request, BidReportService $reports)
    {
        $this->authorize('viewAny', BidNotice::class);

        $csv = $reports->analysisCsv($this->filters($request));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="licitacoes-analises-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'company_id' => $request->input('company_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'verdict' => $request->input('verdict') ?: null,
        ];
    }
}
