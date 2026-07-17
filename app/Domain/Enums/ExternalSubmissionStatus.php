<?php

namespace App\Domain\Enums;

enum ExternalSubmissionStatus: string
{
    case Recebido = 'recebido';
    case Processado = 'processado';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Recebido => 'Recebido',
            self::Processado => 'Processado',
            self::Descartado => 'Descartado',
        };
    }
}
