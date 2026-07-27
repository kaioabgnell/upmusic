<?php

namespace App\Domain\DTOs;

use App\Domain\Enums\BidMatchStatus;

/**
 * Resultado da conferência de um requisito para uma empresa (ver specs/21 §10.3).
 * Imutável e sem I/O: é o que o scorer consome e o que vira linha em `bid_requirement_matches`.
 */
class BidMatchResult
{
    public function __construct(
        public readonly BidMatchStatus $status,
        public readonly string $reason,
        public readonly string $confidence = 'alta',
        public readonly ?int $documentId = null,
        /** Vencimento crítico (≤ critical_days) — pesa menos no score. */
        public readonly bool $critical = false,
        /** Dias até o vencimento do documento usado; null quando não se aplica. */
        public readonly ?int $daysToExpire = null,
    ) {}

    public static function atendido(string $reason, ?int $documentId = null, string $confidence = 'alta', ?int $daysToExpire = null): self
    {
        return new self(BidMatchStatus::Atendido, $reason, $confidence, $documentId, false, $daysToExpire);
    }

    public static function ausente(string $reason, string $confidence = 'alta'): self
    {
        return new self(BidMatchStatus::Ausente, $reason, $confidence);
    }

    public static function conferir(string $reason, ?int $documentId = null, string $confidence = 'media'): self
    {
        return new self(BidMatchStatus::Conferir, $reason, $confidence, $documentId);
    }

    public static function naoAplicavel(string $reason): self
    {
        return new self(BidMatchStatus::NaoAplicavel, $reason);
    }

    public function toAttributes(): array
    {
        return [
            'status' => $this->status->value,
            'confidence' => $this->confidence,
            'reason' => mb_substr($this->reason, 0, 255),
            'bid_document_id' => $this->documentId,
        ];
    }
}
