<?php

namespace App\Domain\Enums;

/** Estado da planilha financeira do evento (specs/23 §4.1). */
enum FinanceSheetStatus: string
{
    case Aberto = 'aberto';
    case Fechado = 'fechado';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Fechado => 'Fechado',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Aberto => 'orange',
            self::Fechado => 'dark',
        };
    }
}
