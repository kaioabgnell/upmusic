<?php

namespace App\Domain\Enums;

enum AttachmentKind: string
{
    case Geral = 'geral';
    case NotaFiscal = 'nota_fiscal';
    case Comprovante = 'comprovante';
    case Orcamento = 'orcamento';
    case Minuta = 'minuta';

    public function label(): string
    {
        return match ($this) {
            self::Geral => 'Geral',
            self::NotaFiscal => 'Nota fiscal',
            self::Comprovante => 'Comprovante',
            self::Orcamento => 'Orçamento',
            self::Minuta => 'Minuta',
        };
    }
}
