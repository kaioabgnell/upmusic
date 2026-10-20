<?php

namespace App\Domain\Enums;

/** Motivo de exclusão de um item de locação (specs/24 §4.1 / §2 Q11-Q22). "Outros" exige texto. */
enum RentalItemDeletionReason: string
{
    case Venda = 'venda';
    case Perda = 'perda';
    case Avaria = 'avaria';
    case Outros = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::Venda => 'Venda',
            self::Perda => 'Perda',
            self::Avaria => 'Avaria sem conserto',
            self::Outros => 'Outros',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
