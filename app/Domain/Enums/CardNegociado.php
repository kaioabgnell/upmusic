<?php

namespace App\Domain\Enums;

enum CardNegociado: string
{
    case SemNota = 'sem_nota';
    case ComNota = 'com_nota';

    public function label(): string
    {
        return match ($this) {
            self::SemNota => 'Negociado sem nota',
            self::ComNota => 'Negociado com nota',
        };
    }
}
