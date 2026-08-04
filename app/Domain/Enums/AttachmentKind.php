<?php

namespace App\Domain\Enums;

enum AttachmentKind: string
{
    case Geral = 'geral';
    case NotaFiscal = 'nota_fiscal';
    case Comprovante = 'comprovante';
    case Orcamento = 'orcamento';
    case Contrato = 'contrato';
    case Minuta = 'minuta';

    public function label(): string
    {
        return match ($this) {
            self::Geral => 'Geral',
            self::NotaFiscal => 'Nota fiscal',
            self::Comprovante => 'Comprovante',
            self::Orcamento => 'Orçamento',
            self::Contrato => 'Contrato',
            self::Minuta => 'Minuta',
        };
    }

    /**
     * Tipos que o usuário escolhe ao anexar um arquivo no card. `Minuta` fica de fora de propósito:
     * é atribuída pelo sistema quando o fornecedor envia pelo link do formulário (specs/19), e
     * deixá-la selecionável permitiria marcar à mão um anexo como se tivesse vindo do fornecedor.
     *
     * @return array<self>
     */
    public static function selectable(): array
    {
        return [self::Geral, self::Orcamento, self::Contrato, self::NotaFiscal, self::Comprovante];
    }
}
