<?php

namespace App\Actions\Bid;

use App\Domain\DTOs\BidMatchResult;
use App\Domain\Enums\BidMatchStatus;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidNotice;
use App\Models\BidNoticeEvaluation;
use App\Models\BidRequirementMatch;
use App\Services\Bid\AptitudeScorer;
use App\Services\Bid\RequirementMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reavaliação determinística de um edital (ver specs/21 §10.3/§10.4).
 *
 * Não chama IA: é gratuita, idempotente e pode rodar a cada abertura da análise, ao salvar um
 * override ou sob demanda. Overrides manuais são preservados — o motor só reescreve as linhas
 * automáticas.
 */
class EvaluateNotice
{
    public function __construct(
        private readonly RequirementMatcher $matcher,
        private readonly AptitudeScorer $scorer,
    ) {}

    /**
     * @param  Collection<int,BidCompany>|null  $companies  empresas a avaliar; null = as já avaliadas
     *                                                      ou, na primeira vez, todas as ativas
     */
    public function execute(BidNotice $notice, ?Collection $companies = null): BidNotice
    {
        $requirements = $notice->requirements()->with(['type', 'category'])->get();
        $companies ??= $this->companiesToEvaluate($notice);

        if ($companies->isEmpty() || $requirements->isEmpty()) {
            return $notice->fresh();
        }

        $documentsByCompany = $this->currentDocuments($companies->pluck('id'));
        $estimatedValue = $notice->estimated_value !== null ? (float) $notice->estimated_value : null;

        // Overrides existentes: (requirement_id => (company_id => match)).
        $overrides = BidRequirementMatch::query()
            ->whereIn('bid_notice_requirement_id', $requirements->pluck('id'))
            ->where('manual_override', true)
            ->get()
            ->groupBy('bid_notice_requirement_id');

        $results = collect();

        DB::transaction(function () use ($notice, $requirements, $companies, $documentsByCompany, $estimatedValue, $overrides, &$results) {
            foreach ($companies as $company) {
                $documents = $documentsByCompany->get($company->id, collect());
                $rows = collect();

                foreach ($requirements as $requirement) {
                    $override = $overrides->get($requirement->id)?->firstWhere('bid_company_id', $company->id);

                    $result = $override
                        ? $this->fromOverride($override)
                        : $this->matcher->match($requirement, $company, $documents, $estimatedValue);

                    if (! $override) {
                        BidRequirementMatch::updateOrCreate(
                            [
                                'bid_notice_requirement_id' => $requirement->id,
                                'bid_company_id' => $company->id,
                            ],
                            $result->toAttributes() + ['manual_override' => false, 'overridden_by' => null]
                        );
                    }

                    $rows->push(['requirement' => $requirement, 'result' => $result]);
                }

                $results->push([
                    'company' => $company,
                    'result' => $this->scorer->evaluate($notice, $company, $rows),
                ]);
            }

            foreach ($this->scorer->rank($results) as $index => $row) {
                $this->persistEvaluation($notice, $row['company'], $row['result'], $index + 1);
            }
        });

        return $notice->fresh(['evaluations.company']);
    }

    // Internos --------------------------------------------------------------

    /** Empresas já avaliadas; na primeira avaliação, todas as ativas. */
    private function companiesToEvaluate(BidNotice $notice): Collection
    {
        $evaluated = $notice->evaluations()->pluck('bid_company_id');

        $query = BidCompany::query()->with('businessLines');

        return $evaluated->isNotEmpty()
            ? $query->whereIn('id', $evaluated)->get()
            : $query->active()->get();
    }

    /**
     * Acervo vigente das empresas em uma única query, agrupado por empresa (evita N+1 — §13).
     *
     * @return Collection<int,Collection<int,BidDocument>>
     */
    private function currentDocuments(Collection $companyIds): Collection
    {
        return BidDocument::query()
            ->current()
            ->whereIn('bid_company_id', $companyIds)
            ->with('type')
            ->get()
            ->groupBy('bid_company_id');
    }

    /** Override manual entra no cálculo como está — a decisão humana é soberana. */
    private function fromOverride(BidRequirementMatch $match): BidMatchResult
    {
        $document = $match->bid_document_id ? BidDocument::find($match->bid_document_id) : null;

        return new BidMatchResult(
            status: $match->status,
            reason: $match->reason ?: 'Definido manualmente.',
            confidence: $match->confidence,
            documentId: $match->bid_document_id,
            critical: (bool) $document?->is_critical,
            daysToExpire: $document?->days_to_expire,
        );
    }

    /**
     * Grava a avaliação preservando `verdict_at_analysis`/`score_at_analysis` — congelados na
     * primeira avaliação, eles são a base do relatório histórico (§6.8).
     */
    private function persistEvaluation(BidNotice $notice, BidCompany $company, $result, int $rank): void
    {
        $evaluation = BidNoticeEvaluation::firstOrNew([
            'bid_notice_id' => $notice->id,
            'bid_company_id' => $company->id,
        ]);

        $evaluation->fill($result->toAttributes() + [
            'rank' => $rank,
            'evaluated_at' => now(),
        ]);

        if ($evaluation->verdict_at_analysis === null) {
            $evaluation->verdict_at_analysis = $result->verdict;
            $evaluation->score_at_analysis = round($result->score, 2);
        }

        $evaluation->save();
    }

    /** Conveniência para a UI: contagem de vínculos de baixa confiança (a confirmar). */
    public static function lowConfidenceCount(BidNotice $notice, BidCompany $company): int
    {
        return BidRequirementMatch::query()
            ->whereIn('bid_notice_requirement_id', $notice->requirements()->pluck('id'))
            ->where('bid_company_id', $company->id)
            ->where('confidence', 'baixa')
            ->whereIn('status', [BidMatchStatus::Atendido->value, BidMatchStatus::Vencendo->value])
            ->count();
    }
}
