<?php

namespace App\Domain\Enums;

enum EventTipo: string
{
    case Publico = 'publico';
    case Privado = 'privado';

    public function label(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Privado => 'Privado',
        };
    }
}
