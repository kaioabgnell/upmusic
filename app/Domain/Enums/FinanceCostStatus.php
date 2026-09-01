<?php

namespace App\Domain\Enums;

/**
 * Coluna STATUS da aba CUSTOS. Os rótulos são exatamente os da lista suspensa do arquivo
 * `FINANCEIRO - MODELO.xlsx` (data validation em C8:C183) — ver specs/23 §2.
 */
enum FinanceCostStatus: string
{
    case Orcamento = 'orcamento';
    case AguardandoContrato = 'aguardando_contrato';
    case ContratoFaltaNota = 'contrato_ok_falta_nota';
    case ContratoNotaOk = 'contrato_ok_nota_ok';
    case NaoAplicado = 'nao_aplicado';

    public function label(): string
    {
        return match ($this) {
            self::Orcamento => 'Orçamento',
            self::AguardandoContrato => 'Aguardando contrato',
            self::ContratoFaltaNota => 'Contrato OK | Falta nota',
            self::ContratoNotaOk => 'Contrato OK | Nota OK',
            self::NaoAplicado => 'Não aplicado',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Orcamento => 'neutral',
            self::AguardandoContrato => 'orange',
            self::ContratoFaltaNota => 'orange',
            self::ContratoNotaOk => 'success',
            self::NaoAplicado => 'neutral',
        };
    }

    /** Rótulo como aparece na planilha — usado no export e no import. */
    public function spreadsheetLabel(): string
    {
        return match ($this) {
            self::Orcamento => 'ORÇAMENTO',
            self::AguardandoContrato => 'AGUARDANDO CONTRATO',
            self::ContratoFaltaNota => 'CONTRATO OK | FALTA NOTA',
            self::ContratoNotaOk => 'CONTRATO OK | NOTA OK',
            self::NaoAplicado => 'NÃO APLICADO',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
