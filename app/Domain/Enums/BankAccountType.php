<?php

namespace App\Domain\Enums;

/** Tipo da conta bancária (specs/26). */
enum BankAccountType: string
{
    case Corrente = 'corrente';
    case Poupanca = 'poupanca';
    case Investimento = 'investimento';

    public function label(): string
    {
        return match ($this) {
            self::Corrente => 'Conta corrente',
            self::Poupanca => 'Poupança',
            self::Investimento => 'Conta investimento',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
