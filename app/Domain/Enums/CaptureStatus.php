<?php

namespace App\Domain\Enums;

enum CaptureStatus: string
{
    case Pendente = 'pendente';
    case Processado = 'processado';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Processado => 'Processado',
            self::Descartado => 'Descartado',
        };
    }
}
