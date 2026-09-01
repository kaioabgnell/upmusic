<?php

namespace App\Domain\Enums;

/**
 * O bloco CONTROLE da planilha (colunas V-AA): os seis documentos que provam a despesa.
 * É o mesmo conjunto que já chega como anexo no card — ver o mapa em
 * `FinanceDocumentKind::fromAttachmentKind()`.
 */
enum FinanceDocumentKind: string
{
    case Orcamento = 'orcamento';
    case Contrato = 'contrato';
    case NotaFiscal = 'nota_fiscal';
    case Comprovante = 'comprovante';
    case Art = 'art';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::Contrato => 'Contrato',
            self::NotaFiscal => 'Nota fiscal',
            self::Comprovante => 'Comprovante',
            self::Art => 'ART',
            self::Boleto => 'Boleto',
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
        };
    }

    /**
     * Mapa anexo do card -> documento do financeiro (specs/23 §6.4).
     *
     * `geral` e `minuta` ficam de fora de propósito: minuta é a PROPOSTA do fornecedor (specs/19),
     * não o contrato assinado — promovê-la marcaria o controle "CONTRATO" como resolvido antes de
     * existir contrato, que é justamente o erro que a prestação de contas precisa evitar.
     */
    public static function fromAttachmentKind(AttachmentKind $kind): ?self
    {
        return match ($kind) {
            AttachmentKind::Orcamento => self::Orcamento,
            AttachmentKind::Contrato => self::Contrato,
            AttachmentKind::NotaFiscal => self::NotaFiscal,
            AttachmentKind::Comprovante => self::Comprovante,
            AttachmentKind::Art => self::Art,
            AttachmentKind::Boleto => self::Boleto,
            AttachmentKind::Geral, AttachmentKind::Minuta => null,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
