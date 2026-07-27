<?php

namespace App\Domain\DTOs;

use App\Domain\Enums\BidVerdict;

/** Aptidão calculada de uma empresa para um edital (ver specs/21 §10.4). */
class BidAptitudeResult
{
    public function __construct(
        public readonly BidVerdict $verdict,
        public readonly float $score,
        public readonly int $metCount,
        public readonly int $expiringCount,
        public readonly int $missingCount,
        public readonly int $reviewCount,
        /** Motivos eliminatórios, já em PT-BR e prontos para exibir. */
        public readonly array $blockers,
        /** Motivos favoráveis, idem. */
        public readonly array $highlights,
        /** Menor folga de vencimento entre os documentos usados — desempate do ranking. */
        public readonly ?int $minDaysToExpire = null,
    ) {}

    public function toAttributes(): array
    {
        return [
            'verdict' => $this->verdict->value,
            'score' => round($this->score, 2),
            'met_count' => $this->metCount,
            'expiring_count' => $this->expiringCount,
            'missing_count' => $this->missingCount,
            'review_count' => $this->reviewCount,
            'blockers' => $this->blockers,
            'highlights' => $this->highlights,
        ];
    }
}
