<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\FinancialEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agregações do comparativo Previsto x Realizado. Cálculos feitos no banco (SUM),
 * conforme specs/09.
 */
class FinancialReportService
{
    /**
     * @param  array{empresa_id?:int|null,category?:string|null,year?:int|null,month?:int|null,plan_id?:int|null}  $filters
     */
    public function summary(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->selectRaw('COALESCE(SUM(estimated_value),0) est, COALESCE(SUM(actual_value),0) act, COUNT(*) total')
            ->first();

        return $this->withDeviation((float) $row->est, (float) $row->act) + ['count' => (int) $row->total];
    }

    /** @return array<int,array> */
    public function byCategory(array $filters): array
    {
        return $this->baseQuery($filters)
            ->selectRaw("COALESCE(NULLIF(category,''),'Sem categoria') as label, SUM(estimated_value) est, SUM(actual_value) act")
            ->groupBy('label')
            ->orderByDesc('est')
            ->get()
            ->map(fn ($r) => ['label' => $r->label] + $this->withDeviation((float) $r->est, (float) $r->act))
            ->all();
    }

    /** @return array<int,array> */
    public function byEmpresa(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->selectRaw('empresa_id, SUM(estimated_value) est, SUM(actual_value) act')
            ->groupBy('empresa_id')
            ->get();

        $names = Empresa::whereIn('id', $rows->pluck('empresa_id')->filter())->pluck('corporate_name', 'id');

        return $rows->map(fn ($r) => [
            'label' => $r->empresa_id ? ($names[$r->empresa_id] ?? 'Empresa') : 'Sem empresa',
        ] + $this->withDeviation((float) $r->est, (float) $r->act))
            ->sortByDesc('estimated')->values()->all();
    }

    private function baseQuery(array $filters): Builder
    {
        return FinancialEntry::query()
            ->when($filters['plan_id'] ?? null, fn ($q, $v) => $q->where('financial_plan_id', $v))
            ->when($filters['empresa_id'] ?? null, fn ($q, $v) => $q->where('empresa_id', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->whereYear('estimated_date', $v)->orWhereYear('actual_date', $v)))
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->whereMonth('estimated_date', $v)->orWhereMonth('actual_date', $v)));
    }

    private function withDeviation(float $est, float $act): array
    {
        $deviation = $act - $est;
        $pct = $est > 0 ? round($act / $est * 100, 1) : null;

        return [
            'estimated' => $est,
            'actual' => $act,
            'deviation' => $deviation,
            'pct' => $pct,
        ];
    }
}
