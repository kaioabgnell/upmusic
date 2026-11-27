<?php

namespace App\Domain\Enums;

/** Seções da tela de Perfil de acesso (specs/28 §3), na ordem em que aparecem. */
enum AccessModuleGroup: string
{
    case Quadros = 'quadros';
    case Cadastros = 'cadastros';
    case Financeiro = 'financeiro';
    case Operacao = 'operacao';

    public function label(): string
    {
        return match ($this) {
            self::Quadros => 'Quadros',
            self::Cadastros => 'Cadastros',
            self::Financeiro => 'Financeiro',
            self::Operacao => 'Operação',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Quadros => 'fa-table-columns',
            self::Cadastros => 'fa-folder-open',
            self::Financeiro => 'fa-sack-dollar',
            self::Operacao => 'fa-gears',
        };
    }
}
