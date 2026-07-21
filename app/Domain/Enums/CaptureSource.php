<?php

namespace App\Domain\Enums;

enum CaptureSource: string
{
    case Upload = 'upload';
    case PwaShare = 'pwa_share';
    case IosShortcut = 'ios_shortcut';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Envio manual',
            self::PwaShare => 'Compartilhar (Android)',
            self::IosShortcut => 'Atalho (iPhone)',
        };
    }
}
