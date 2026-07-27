<?php

namespace App\Services\Bid;

use App\Domain\Enums\BidMatchStatus;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidNotice;
use App\Models\BidNoticeEvaluation;
use App\Models\BidRequirementMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios de dados passados do módulo (ver specs/21 §9.7).
 *
 * O histórico usa `verdict_at_analysis`/`score_at_analysis` — o que valia quando o edital foi
 * analisado — e não os valores atuais, que mudam a cada renovação de certidão.
 */
class BidReportService
{
    /** Filtros aceitos: from, to, company_id, category_id, verdict. */
    public function analysisHistory(array $filters): Collection
    {
        return BidNotice::query()
            ->with(['evaluations' => fn ($q) => $q->orderBy('rank')->with('company')])
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'evaluations',
                fn ($e) => $e->where('bid_company_id', $id)
            ))
            ->when($filters['verdict'] ?? null, fn ($q, $verdict) => $q->whereHas(
                'evaluations',
                fn ($e) => $e->where('verdict_at_analysis', $verdict)
            ))
            ->latest()
            ->limit(200)
            ->get();
    }

    /**
     * Aptidão por empresa no período: quantas vezes ficou apta / com pendências / inapta e o
     * score médio, sempre pelos valores congelados na análise.
     */
    public function aptitudeByCompany(array $filters): Collection
    {
        $rows = BidNoticeEvaluation::query()
            ->join('bid_notices', 'bid_notices.id', '=', 'bid_notice_evaluations.bid_notice_id')
            ->whereNull('bid_notices.deleted_at')
            ->whereNotNull('verdict_at_analysis')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('bid_notices.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('bid_notices.created_at', '<=', $to))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('bid_company_id', $id))
            ->select('bid_company_id')
            ->selectRaw("SUM(CASE WHEN verdict_at_analysis = 'apta' THEN 1 ELSE 0 END) as aptas")
            ->selectRaw("SUM(CASE WHEN verdict_at_analysis = 'apta_com_pendencias' THEN 1 ELSE 0 END) as pendencias")
            ->selectRaw("SUM(CASE WHEN verdict_at_analysis = 'inapta' THEN 1 ELSE 0 END) as inaptas")
            ->selectRaw('COUNT(*) as total, AVG(score_at_analysis) as media')
            ->groupBy('bid_company_id')
            ->get();

        $companies = BidCompany::query()->whereIn('id', $rows->pluck('bid_company_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'company' => $companies->get($row->bid_company_id),
            'aptas' => (int) $row->aptas,
            'pendencias' => (int) $row->pendencias,
            'inaptas' => (int) $row->inaptas,
            'total' => (int) $row->total,
            'media' => round((float) $row->media, 1),
        ])->filter(fn ($row) => $row['company'] !== null)
            ->sortByDesc('media')
            ->values();
    }

    /** Requisitos que mais bloquearam empresas — onde investir em documentação. */
    public function topBlockers(array $filters, int $limit = 12): Collection
    {
        return BidRequirementMatch::query()
            ->join('bid_notice_requirements as r', 'r.id', '=', 'bid_requirement_matches.bid_notice_requirement_id')
            ->join('bid_notices as n', 'n.id', '=', 'r.bid_notice_id')
            ->whereNull('n.deleted_at')
            ->where('r.mandatory', true)
            ->whereIn('bid_requirement_matches.status', [BidMatchStatus::Ausente->value, BidMatchStatus::Vencido->value])
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('n.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('n.created_at', '<=', $to))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('bid_requirement_matches.bid_company_id', $id))
            ->select('r.name', 'r.kind')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT r.bid_notice_id) as editais')
            ->groupBy('r.name', 'r.kind')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * Conformidade documental por empresa: o que está vencido hoje, quantas vezes documentos
     * venceram no período e quanto tempo se levou, em média, para renovar depois do vencimento.
     */
    public function documentCompliance(array $filters): Collection
    {
        $companies = BidCompany::query()
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('corporate_name')
            ->get();

        $expiredNow = BidDocument::query()
            ->current()
            ->status('vencido')
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('bid_document_category_id', $id))
            ->select('bid_company_id', DB::raw('COUNT(*) as total'))
            ->groupBy('bid_company_id')
            ->pluck('total', 'bid_company_id');

        // Documentos que foram substituídos DEPOIS de já estarem vencidos: cada linha é uma vez
        // em que a empresa ficou irregular naquele documento.
        $lateRenewals = BidDocument::query()
            ->withTrashed()
            ->whereNotNull('superseded_at')
            ->whereNotNull('expires_at')
            ->whereColumn('superseded_at', '>', 'expires_at')
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('superseded_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('superseded_at', '<=', $to))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('bid_document_category_id', $id))
            ->select('bid_company_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(DATEDIFF(superseded_at, expires_at)) as dias_medios')
            ->groupBy('bid_company_id')
            ->get()
            ->keyBy('bid_company_id');

        return $companies->map(fn (BidCompany $company) => [
            'company' => $company,
            'vencidos_hoje' => (int) ($expiredNow[$company->id] ?? 0),
            'vencimentos_periodo' => (int) ($lateRenewals[$company->id]->total ?? 0),
            'dias_medios' => $lateRenewals[$company->id]->dias_medios ?? null,
        ]);
    }

    /** Vencimentos dos próximos N dias, agrupados por mês. */
    public function upcomingExpirations(int $days = 90, array $filters = []): Collection
    {
        return BidDocument::query()
            ->current()
            ->where('no_expiry', false)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [Carbon::today()->toDateString(), Carbon::today()->addDays($days)->toDateString()])
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('bid_company_id', $id))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('bid_document_category_id', $id))
            ->with(['company', 'category'])
            ->orderBy('expires_at')
            ->get()
            ->groupBy(fn (BidDocument $doc) => $doc->expires_at->translatedFormat('F/Y'));
    }

    /**
     * CSV do histórico de análises, no padrão do projeto: `;`, UTF-8 com BOM, datas d/m/Y e
     * decimais com vírgula (ver FinancialReportController::export).
     */
    public function analysisCsv(array $filters): string
    {
        $lines = ["\u{FEFF}".implode(';', [
            'Edital', 'Órgão', 'Número', 'Modalidade', 'Sessão', 'Valor estimado',
            'Analisado em', 'Empresa mais apta', 'Veredito na análise', 'Score na análise', 'Bloqueios',
        ])];

        foreach ($this->analysisHistory($filters) as $notice) {
            $top = $notice->evaluations->first();

            $lines[] = implode(';', array_map([$this, 'csvCell'], [
                $notice->title,
                $notice->agency,
                $notice->number,
                $notice->modality,
                $notice->session_at?->format('d/m/Y H:i'),
                $notice->estimated_value !== null ? number_format((float) $notice->estimated_value, 2, ',', '') : null,
                $notice->created_at?->format('d/m/Y'),
                $top?->company?->corporate_name,
                $top?->verdict_at_analysis?->label(),
                $top ? number_format((float) $top->score_at_analysis, 2, ',', '') : null,
                $top ? count($top->blockers ?? []) : null,
            ]));
        }

        return implode("\r\n", $lines);
    }

    private function csvCell(mixed $value): string
    {
        $clean = str_replace([';', "\r", "\n", '"'], [',', ' ', ' ', "'"], (string) ($value ?? ''));

        return $clean;
    }
}
