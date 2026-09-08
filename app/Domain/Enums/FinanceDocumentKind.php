<?php

namespace App\Domain\Enums;

/**
 * Tipo do documento de uma linha de custo — o bloco CONTROLE da planilha (colunas V-AA).
 *
 * Espelha 1:1 o `AttachmentKind` do card: o tipo escolhido na hora de anexar o arquivo é o mesmo
 * que vale no Financeiro, sem reclassificar nada. Os seis primeiros casos são, na ordem, as seis
 * colunas do arquivo modelo; `Geral` e `Minuta` vêm depois porque existem no card mas não provam
 * despesa.
 */
enum FinanceDocumentKind: string
{
    case Orcamento = 'orcamento';
    case Contrato = 'contrato';
    case NotaFiscal = 'nota_fiscal';
    case Comprovante = 'comprovante';
    case Art = 'art';
    case Boleto = 'boleto';
    case Geral = 'geral';
    case Minuta = 'minuta';

    public function label(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::Contrato => 'Contrato',
            self::NotaFiscal => 'Nota fiscal',
            self::Comprovante => 'Comprovante',
            self::Art => 'ART',
            self::Boleto => 'Boleto',
            self::Geral => 'Geral',
            self::Minuta => 'Minuta',
        };
    }

    /** Rótulo curto do "chip" na grade de custos. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Orcamento => 'Orç.',
            self::Contrato => 'Contr.',
            self::NotaFiscal => 'NF',
            self::Comprovante => 'Compr.',
            self::Art => 'ART',
            self::Boleto => 'Bol.',
            self::Geral => 'Geral',
            self::Minuta => 'Min.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Orcamento => 'fa-file-invoice-dollar',
            self::Contrato => 'fa-file-signature',
            self::NotaFiscal => 'fa-receipt',
            self::Comprovante => 'fa-circle-check',
            self::Art => 'fa-stamp',
            self::Boleto => 'fa-barcode',
            self::Geral => 'fa-file',
            self::Minuta => 'fa-file-pen',
        };
    }

    /**
     * O tipo do anexo do card É o tipo no Financeiro — mapa total, nunca nulo. O usuário escolhe
     * uma vez, na hora de anexar, e o envio ao Financeiro respeita a escolha.
     *
     * Minuta continua sendo minuta (e não vira "contrato"): ela é a PROPOSTA do fornecedor
     * (specs/19), e só o contrato assinado faz o status da linha avançar — ver DeriveCostItemStatus.
     */
    public static function fromAttachmentKind(AttachmentKind $kind): self
    {
        return match ($kind) {
            AttachmentKind::Orcamento => self::Orcamento,
            AttachmentKind::Contrato => self::Contrato,
            AttachmentKind::NotaFiscal => self::NotaFiscal,
            AttachmentKind::Comprovante => self::Comprovante,
            AttachmentKind::Art => self::Art,
            AttachmentKind::Boleto => self::Boleto,
            AttachmentKind::Geral => self::Geral,
            AttachmentKind::Minuta => self::Minuta,
        };
    }

    /**
     * Tipos que o usuário escolhe ao anexar um arquivo direto no Financeiro. Mesma exclusão do
     * `AttachmentKind::selectable()`: `Minuta` é atribuída pelo sistema quando o fornecedor envia
     * pelo link do formulário (specs/19), e deixá-la selecionável permitiria marcar à mão um
     * arquivo como se tivesse vindo do fornecedor.
     *
     * @return array<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c !== self::Minuta));
    }

    /**
     * Os seis documentos que PROVAM a despesa — as colunas V-AA do arquivo modelo, na ordem.
     * `Geral` e `Minuta` ficam de fora: aparecem no card, mas não são prova de gasto.
     *
     * @return array<self>
     */
    public static function proofKinds(): array
    {
        return [self::Orcamento, self::Contrato, self::NotaFiscal, self::Comprovante, self::Art, self::Boleto];
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
