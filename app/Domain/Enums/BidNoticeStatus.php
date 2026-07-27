<?php

namespace App\Domain\Enums;

/** Ciclo de vida da análise de um edital (ver specs/21 §5.2 e §6.6). */
enum BidNoticeStatus: string
{
    case Rascunho = 'rascunho';
    case Processando = 'processando';
    case Analisado = 'analisado';
    case Erro = 'erro';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Processando => 'Processando',
            self::Analisado => 'Analisado',
            self::Erro => 'Erro',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Rascunho => 'neutral',
            self::Processando => 'orange',
            self::Analisado => 'success',
            self::Erro => 'danger',
        };
    }
}
