<?php

namespace App\Domain\Enums;

enum CardOrigin: string
{
    case Manual = 'manual';
    case Template = 'template';
    case ExternalForm = 'external_form';
    case CapturaRapida = 'captura_rapida';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Template => 'Template',
            self::ExternalForm => 'Formulário externo',
            self::CapturaRapida => 'Captura rápida',
        };
    }
}
