<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\FinancialEntry;
use App\Models\FinancialPlan;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(private FinancialReportService $service) {}

    public function report(Request $request)
    {
        $filters = $this->filters($request);

        return view('financeiro.comparativo', [
            'filters' => $filters,
            'summary' => $this->service->summary($filters),
            'byCategory' => $this->service->byCategory($filters),
            'byEmpresa' => $this->service->byEmpresa($filters),
            'empresas' => Empresa::orderBy('corporate_name')->get(['id', 'corporate_name']),
            'plans' => FinancialPlan::orderByDesc('id')->get(['id', 'name']),
            'categories' => FinancialEntry::query()->whereNotNull('category')->where('category', '!=', '')
                ->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->service->byCategory($filters);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Categoria', 'Previsto', 'Realizado', 'Desvio', '% Realização'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['label'],
                    number_format($r['estimated'], 2, ',', '.'),
                    number_format($r['actual'], 2, ',', '.'),
                    number_format($r['deviation'], 2, ',', '.'),
                    $r['pct'] !== null ? $r['pct'].'%' : '-',
                ], ';');
            }
            fclose($out);
        }, 'comparativo-previsto-realizado.csv', ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return [
            'plan_id' => $request->integer('plan_id') ?: null,
            'empresa_id' => $request->integer('empresa_id') ?: null,
            'category' => $request->input('category') ?: null,
            'year' => $request->integer('year') ?: null,
            'month' => $request->integer('month') ?: null,
        ];
    }
}
