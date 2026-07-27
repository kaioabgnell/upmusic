<?php

namespace App\Domain\Enums;

/** Como o edital entrou no sistema (ver specs/21 §6.6). */
enum BidNoticeSource: string
{
    case Pdf = 'pdf';
    case Imagem = 'imagem';
    case Texto = 'texto';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Imagem => 'Imagem',
            self::Texto => 'Texto colado',
        };
    }
}
