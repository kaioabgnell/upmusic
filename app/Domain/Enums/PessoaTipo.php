<?php

namespace App\Domain\Enums;

enum PessoaTipo: string
{
    case PF = 'PF';
    case PJ = 'PJ';

    public function label(): string
    {
        return match ($this) {
            self::PF => 'Pessoa Física',
            self::PJ => 'Pessoa Jurídica',
        };
    }

    public function documentLabel(): string
    {
        return $this === self::PF ? 'CPF' : 'CNPJ';
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
