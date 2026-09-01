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
    // ART e Boleto entram pelo Financeiro do Evento (specs/23): sem eles, dois dos seis
    // controles documentais da planilha nunca chegariam preenchidos pela ponte card -> financeiro.
    case Art = 'art';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Geral => 'Geral',
            self::NotaFiscal => 'Nota fiscal',
            self::Comprovante => 'Comprovante',
            self::Orcamento => 'Orçamento',
            self::Contrato => 'Contrato',
            self::Minuta => 'Minuta',
            self::Art => 'ART',
            self::Boleto => 'Boleto',
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
        return [self::Geral, self::Orcamento, self::Contrato, self::NotaFiscal, self::Comprovante, self::Art, self::Boleto];
    }
}
