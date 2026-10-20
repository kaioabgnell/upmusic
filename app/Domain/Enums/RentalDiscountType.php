<?php

namespace App\Domain\Enums;

/** Tipo de desconto do pedido de locação (specs/24 §4.2 / §7.1). */
enum RentalDiscountType: string
{
    case Valor = 'valor';
    case Percentual = 'percentual';

    public function label(): string
    {
        return match ($this) {
            self::Valor => 'R$',
            self::Percentual => '%',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
