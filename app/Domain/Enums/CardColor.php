<?php

namespace App\Domain\Enums;

/**
 * Cor de fundo do card no Kanban. Tons claros de propósito: o texto do card é escuro e precisa
 * continuar legível sobre qualquer uma delas. Card sem cor gravada (null) é Branco.
 */
enum CardColor: string
{
    case Branco = 'branco';
    case Cinza = 'cinza';
    case Vermelho = 'vermelho';
    case Laranja = 'laranja';
    case Amarelo = 'amarelo';
    case Verde = 'verde';
    case Ciano = 'ciano';
    case Azul = 'azul';
    case Roxo = 'roxo';
    case Rosa = 'rosa';

    public function label(): string
    {
        return match ($this) {
            self::Branco => 'Branco',
            self::Cinza => 'Cinza',
            self::Vermelho => 'Vermelho',
            self::Laranja => 'Laranja',
            self::Amarelo => 'Amarelo',
            self::Verde => 'Verde',
            self::Ciano => 'Ciano',
            self::Azul => 'Azul',
            self::Roxo => 'Roxo',
            self::Rosa => 'Rosa',
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::Branco => '#ffffff',
            self::Cinza => '#f3f4f6',
            self::Vermelho => '#fee2e2',
            self::Laranja => '#ffedd5',
            self::Amarelo => '#fef9c3',
            self::Verde => '#dcfce7',
            self::Ciano => '#cffafe',
            self::Azul => '#dbeafe',
            self::Roxo => '#ede9fe',
            self::Rosa => '#fce7f3',
        };
    }

    /**
     * Paleta para o seletor do modal do card — a mesma fonte que valida o request, sem lista
     * duplicada no front.
     *
     * @return array<int, array{value: string, label: string, hex: string}>
     */
    public static function palette(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'hex' => $c->hex(),
        ], self::cases());
    }
}
