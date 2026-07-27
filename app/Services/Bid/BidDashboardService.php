<?php

namespace App\Services\Bid;

use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentType;
use App\Models\BidNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Números e alertas do painel de Licitações (ver specs/21 §9.1 e §13).
 *
 * Os contadores saem de UMA query agregada e ficam em cache curto, invalidado sempre que um
 * documento é criado, renovado ou excluído — o badge da sidebar usa o mesmo cache.
 */
class BidDashboardService
{
    private const COUNTERS_KEY = 'licitacoes:counters';

    private const PENDING_KEY = 'licitacoes:pending';

    /** @return array{total:int,validos:int,vencendo:int,vencidos:int,empresas:int} */
    public function counters(): array
    {
        return Cache::remember(self::COUNTERS_KEY, (int) config('licitacoes.dashboard_cache_ttl', 300), function () {
            $row = BidDocument::query()
                ->current()
                ->selectRaw(BidDocument::countExpression('total').' as total')
                ->selectRaw(BidDocument::countExpression('valido').' as validos')
                ->selectRaw(BidDocument::countExpression('vencendo').' as vencendo')
                ->selectRaw(BidDocument::countExpression('vencido').' as vencidos')
                ->first();

            return [
                'total' => (int) ($row->total ?? 0),
                'validos' => (int) ($row->validos ?? 0),
                'vencendo' => (int) ($row->vencendo ?? 0),
                'vencidos' => (int) ($row->vencidos ?? 0),
                'empresas' => BidCompany::query()->active()->count(),
            ];
        });
    }

    /** Documentos vencidos + vencendo, do mais crítico para o menos (§9.1). */
    public function alerts(int $limit = 10): Collection
    {
        return BidDocument::query()
            ->current()
            ->with(['company', 'category'])
            ->where('no_expiry', false)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(BidDocument::expiringDays())->toDateString())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();
    }

    /** Total de documentos que exigem ação (vencidos + vencendo) — badge da sidebar. */
    public function pendingCount(): int
    {
        return Cache::remember(self::PENDING_KEY, (int) config('licitacoes.dashboard_cache_ttl', 300), function () {
            $counters = $this->counters();

            return $counters['vencendo'] + $counters['vencidos'];
        });
    }

    /**
     * Saúde documental por empresa: tipos essenciais do catálogo cobertos por documento vigente
     * e não vencido. É a barra "10/12" do painel e da lista de empresas.
     *
     * @return Collection<int,array{company:BidCompany,total:int,ok:int,percent:int}>
     */
    public function companiesHealth(): Collection
    {
        $essentialIds = BidDocumentType::query()->active()->where('essential', true)->pluck('id');
        $total = $essentialIds->count();

        $companies = BidCompany::query()->active()->orderBy('corporate_name')->get();

        $covered = BidDocument::query()
            ->current()
            ->usable()
            ->whereIn('bid_document_type_id', $essentialIds)
            ->whereIn('bid_company_id', $companies->pluck('id'))
            ->select('bid_company_id', DB::raw('COUNT(DISTINCT bid_document_type_id) as ok'))
            ->groupBy('bid_company_id')
            ->pluck('ok', 'bid_company_id');

        return $companies->map(function (BidCompany $company) use ($covered, $total) {
            $ok = (int) ($covered[$company->id] ?? 0);

            return [
                'company' => $company,
                'total' => $total,
                'ok' => $ok,
                'percent' => $total > 0 ? (int) round(100 * $ok / $total) : 0,
            ];
        });
    }

    /** Últimas análises de edital, com a empresa mais apta de cada uma. */
    public function recentNotices(int $limit = 5): Collection
    {
        return BidNotice::query()
            ->with(['evaluations' => fn ($q) => $q->where('rank', 1)->with('company')])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** Chamado sempre que o acervo muda — o painel nunca mostra número velho. */
    public static function forget(): void
    {
        Cache::forget(self::COUNTERS_KEY);
        Cache::forget(self::PENDING_KEY);
    }
}
