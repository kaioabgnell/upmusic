<?php

namespace App\Domain\Enums;

/** Porte da empresa licitante — critério eliminatório em itens exclusivos ME/EPP (specs/21). */
enum BidCompanySize: string
{
    case Me = 'me';
    case Epp = 'epp';
    case Demais = 'demais';

    public function label(): string
    {
        return match ($this) {
            self::Me => 'Microempresa (ME)',
            self::Epp => 'Empresa de Pequeno Porte (EPP)',
            self::Demais => 'Demais empresas',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Me => 'ME',
            self::Epp => 'EPP',
            self::Demais => 'Demais',
        };
    }
}
