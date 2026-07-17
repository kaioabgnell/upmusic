<?php

namespace App\Domain\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Coordenador = 'coordenador';
    case Usuario = 'usuario';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Coordenador => 'Coordenador',
            self::Usuario => 'Usuário',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
