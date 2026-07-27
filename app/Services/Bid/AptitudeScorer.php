<?php

namespace App\Services\Bid;

use App\Domain\DTOs\BidAptitudeResult;
use App\Domain\DTOs\BidMatchResult;
use App\Domain\Enums\BidMatchStatus;
use App\Domain\Enums\BidVerdict;
use App\Models\BidCompany;
use App\Models\BidNotice;
use App\Models\BidNoticeRequirement;
use App\Support\BidText;
use Illuminate\Support\Collection;

/**
 * Pontuação e veredito de aptidão (ver specs/21 §10.4) — determinístico e reprodutível.
 *
 * Peso: obrigatório 3, opcional 1. Crédito: atendido 1,0 · vencendo 0,75 (crítico 0,5) · vencido
 * ou ausente 0. Requisitos `conferir`/`nao_aplicavel` ficam FORA do denominador e aparecem como
 * pendência — nunca como aprovação silenciosa.
 */
class AptitudeScorer
{
    /**
     * @param  Collection<int,array{requirement: BidNoticeRequirement, result: BidMatchResult}>  $rows
     */
    public function evaluate(BidNotice $notice, BidCompany $company, Collection $rows): BidAptitudeResult
    {
        $weighted = 0.0;
        $totalWeight = 0.0;
        $met = $expiring = $missing = $review = 0;
        $blockers = [];
        $optionalPending = 0;
        $mandatoryReview = 0;
        $minDays = null;

        foreach ($rows as $row) {
            /** @var BidNoticeRequirement $requirement */
            $requirement = $row['requirement'];
            /** @var BidMatchResult $result */
            $result = $row['result'];
            $status = $result->status;

            if ($status === BidMatchStatus::NaoAplicavel) {
                continue;
            }

            if ($status->countsForScore()) {
                $totalWeight += $requirement->weight;
                $weighted += $requirement->weight * $status->credit($result->critical);
            }

            match ($status) {
                BidMatchStatus::Atendido => $met++,
                BidMatchStatus::Vencendo => $expiring++,
                BidMatchStatus::Vencido, BidMatchStatus::Ausente => $missing++,
                BidMatchStatus::Conferir => $review++,
                default => null,
            };

            if ($requirement->mandatory && $status->isBlocking()) {
                $blockers[] = $this->blockerMessage($requirement, $result);
            }

            if ($requirement->mandatory && $status === BidMatchStatus::Conferir) {
                $mandatoryReview++;
            }

            if (! $requirement->mandatory && $status->isBlocking()) {
                $optionalPending++;
            }

            if ($result->daysToExpire !== null) {
                $minDays = $minDays === null ? $result->daysToExpire : min($minDays, $result->daysToExpire);
            }
        }

        $score = $totalWeight > 0 ? 100 * ($weighted / $totalWeight) : 0.0;

        $verdict = match (true) {
            $blockers !== [] => BidVerdict::Inapta,
            $expiring > 0 || $mandatoryReview > 0 || $optionalPending > 0 => BidVerdict::AptaComPendencias,
            default => BidVerdict::Apta,
        };

        return new BidAptitudeResult(
            verdict: $verdict,
            score: round($score, 2),
            metCount: $met,
            expiringCount: $expiring,
            missingCount: $missing,
            reviewCount: $review,
            blockers: array_values(array_unique($blockers)),
            highlights: $this->highlights($notice, $company, $rows, $verdict),
            minDaysToExpire: $minDays,
        );
    }

    /**
     * Ordenação do ranking (§10.4): veredito → score → menos bloqueadores → menos pendências →
     * maior folga de vencimento → razão social.
     *
     * @param  Collection<int,array{company: BidCompany, result: BidAptitudeResult}>  $evaluations
     */
    public function rank(Collection $evaluations): Collection
    {
        // Comparador explícito com tupla: `Collection::sortBy([callable, ...])` no Laravel 10 usa
        // cada callable como COMPARADOR ($a, $b), não como extrator de valor — passar extratores
        // ali ordena por acidente.
        $key = fn (array $row): array => [
            $row['result']->verdict->tier(),
            -$row['result']->score,
            count($row['result']->blockers),
            $row['result']->missingCount + $row['result']->expiringCount + $row['result']->reviewCount,
            // Maior folga primeiro: menos risco de vencer antes da sessão.
            -($row['result']->minDaysToExpire ?? PHP_INT_MAX),
            $row['company']->corporate_name,
        ];

        return $evaluations->sort(fn (array $a, array $b) => $key($a) <=> $key($b))->values();
    }

    // Mensagens -------------------------------------------------------------

    /** O motivo do matcher já é uma frase pronta; aqui só recebe o rótulo do bloqueio. */
    private function blockerMessage(BidNoticeRequirement $requirement, BidMatchResult $result): string
    {
        $prefix = $requirement->kind->isStructural()
            ? 'Requisito da empresa não atendido'
            : ($result->status === BidMatchStatus::Vencido ? 'Documento obrigatório vencido' : 'Documento obrigatório ausente');

        return "{$prefix}: {$result->reason}";
    }

    /**
     * Motivos favoráveis, em PT-BR e prontos para exibir. A afinidade de ramo é o desempate por
     * vocação: é o que diferencia a empresa de eventos da de portaria (§10.4).
     *
     * @param  Collection<int,array{requirement: BidNoticeRequirement, result: BidMatchResult}>  $rows
     */
    private function highlights(BidNotice $notice, BidCompany $company, Collection $rows, BidVerdict $verdict): array
    {
        $highlights = [];

        if ($verdict === BidVerdict::Apta) {
            $highlights[] = 'Todos os requisitos obrigatórios atendidos.';
        }

        foreach ($rows as $row) {
            /** @var BidNoticeRequirement $requirement */
            $requirement = $row['requirement'];
            /** @var BidMatchResult $result */
            $result = $row['result'];

            if ($result->status !== BidMatchStatus::Atendido || ! $requirement->kind->isStructural()) {
                continue;
            }

            // O motivo do matcher já explica o atendimento ("CNAE 8121-4/00 consta no cadastro").
            $highlights[] = $result->reason;
        }

        foreach ($this->matchingBusinessLines($notice, $company) as $line) {
            $highlights[] = "Atua em {$line}, compatível com o objeto do edital.";
        }

        return array_values(array_unique($highlights));
    }

    /** Ramos da empresa cujas palavras-chave aparecem no objeto do edital. */
    private function matchingBusinessLines(BidNotice $notice, BidCompany $company): array
    {
        $object = BidText::normalize($notice->object_summary.' '.$notice->title);

        if ($object === '' || ! $company->relationLoaded('businessLines')) {
            return [];
        }

        $matched = [];

        foreach ($company->businessLines as $line) {
            foreach ($line->keywords ?? [] as $keyword) {
                $normalized = BidText::normalize($keyword);

                if ($normalized !== '' && str_contains($object, $normalized)) {
                    $matched[] = $line->name;
                    break;
                }
            }
        }

        return $matched;
    }
}
