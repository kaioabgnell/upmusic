<?php

namespace App\Domain\Enums;

/** Veredito de aptidão de uma empresa para um edital (ver specs/21 §10.4). */
enum BidVerdict: string
{
    case Apta = 'apta';
    case AptaComPendencias = 'apta_com_pendencias';
    case Inapta = 'inapta';

    public function label(): string
    {
        return match ($this) {
            self::Apta => 'Apta',
            self::AptaComPendencias => 'Apta com pendências',
            self::Inapta => 'Inapta',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Apta => 'fa-circle-check',
            self::AptaComPendencias => 'fa-circle-half-stroke',
            self::Inapta => 'fa-circle-xmark',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Apta => 'success',
            self::AptaComPendencias => 'orange',
            self::Inapta => 'danger',
        };
    }

    /** Ordem no ranking: apta primeiro. */
    public function tier(): int
    {
        return match ($this) {
            self::Apta => 0,
            self::AptaComPendencias => 1,
            self::Inapta => 2,
        };
    }
}
