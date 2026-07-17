<?php

namespace App\Domain\Enums;

enum CardPriority: string
{
    case Baixa = 'baixa';
    case Media = 'media';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::Baixa => 'Baixa',
            self::Media => 'Média',
            self::Alta => 'Alta',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Baixa => 'neutral',
            self::Media => 'orange',
            self::Alta => 'danger',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $p) => [$p->value => $p->label()])
            ->all();
    }
}
