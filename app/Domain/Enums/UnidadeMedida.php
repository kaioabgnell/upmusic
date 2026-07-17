<?php

namespace App\Domain\Enums;

enum UnidadeMedida: string
{
    case Diaria = 'diaria';
    case Unidade = 'unidade';
    case Hora = 'hora';
    case ServicoCompleto = 'servico_completo';

    public function label(): string
    {
        return match ($this) {
            self::Diaria => 'Diária',
            self::Unidade => 'Unidade',
            self::Hora => 'Hora',
            self::ServicoCompleto => 'Serviço completo',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $u) => [$u->value => $u->label()])
            ->all();
    }
}
