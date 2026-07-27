<?php

namespace App\Domain\Enums;

/**
 * Resultado da conferência de um requisito para uma empresa (ver specs/21 §10.3).
 * `Conferir` é a válvula de escape do motor: incerteza nunca vira aprovação.
 */
enum BidMatchStatus: string
{
    case Atendido = 'atendido';
    case Vencendo = 'vencendo';
    case Vencido = 'vencido';
    case Ausente = 'ausente';
    case Conferir = 'conferir';
    case NaoAplicavel = 'nao_aplicavel';

    public function label(): string
    {
        return match ($this) {
            self::Atendido => 'Atendido',
            self::Vencendo => 'Vencendo',
            self::Vencido => 'Vencido',
            self::Ausente => 'Ausente',
            self::Conferir => 'Conferir',
            self::NaoAplicavel => 'Não aplicável',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Atendido => 'fa-circle-check',
            self::Vencendo => 'fa-clock',
            self::Vencido => 'fa-circle-xmark',
            self::Ausente => 'fa-circle-xmark',
            self::Conferir => 'fa-circle-question',
            self::NaoAplicavel => 'fa-minus',
        };
    }

    public function classes(): string
    {
        return match ($this) {
            self::Atendido => 'text-green-600',
            self::Vencendo => 'text-amber-600',
            self::Vencido, self::Ausente => 'text-red-600',
            self::Conferir => 'text-steel',
            self::NaoAplicavel => 'text-gray-300',
        };
    }

    /** Peso do requisito no score (ver specs/21 §10.4). */
    public function credit(bool $critical = false): float
    {
        return match ($this) {
            self::Atendido => 1.0,
            self::Vencendo => $critical ? 0.5 : 0.75,
            default => 0.0,
        };
    }

    /** Entra no denominador do score? `conferir`/`nao_aplicavel` ficam fora. */
    public function countsForScore(): bool
    {
        return ! in_array($this, [self::Conferir, self::NaoAplicavel], true);
    }

    /** Bloqueia a empresa quando o requisito é obrigatório. */
    public function isBlocking(): bool
    {
        return in_array($this, [self::Vencido, self::Ausente], true);
    }

    public static function fromDocumentStatus(BidDocumentStatus $status): self
    {
        return match ($status) {
            BidDocumentStatus::Valido, BidDocumentStatus::Permanente => self::Atendido,
            BidDocumentStatus::Vencendo => self::Vencendo,
            BidDocumentStatus::Vencido => self::Vencido,
        };
    }
}
