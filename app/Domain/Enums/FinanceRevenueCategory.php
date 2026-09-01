<?php

namespace App\Domain\Enums;

/** Coluna RECEITA da aba RECEITAS — as linhas fixas do arquivo modelo (specs/23 §4.6). */
enum FinanceRevenueCategory: string
{
    case IngressosTicketeira = 'ingressos_ticketeira';
    case LoungesTicketeira = 'lounges_ticketeira';
    case LoungesAvulso = 'lounges_avulso';
    case Estacionamento = 'estacionamento';
    case BarEmpresa = 'bar_empresa';
    case BarDrinks = 'bar_drinks';
    case Balinheiros = 'balinheiros';
    case Copos = 'copos';
    case Alimentacao = 'alimentacao';
    case Patrocinio = 'patrocinio';
    case Outros = 'outros';

    public function label(): string
    {
        return match ($this) {
            self::IngressosTicketeira => 'Ingressos | Ticketeira',
            self::LoungesTicketeira => 'Lounges | Ticketeira',
            self::LoungesAvulso => 'Lounges | Avulso',
            self::Estacionamento => 'Estacionamento',
            self::BarEmpresa => 'Bar | Empresa',
            self::BarDrinks => 'Bar | Drinks',
            self::Balinheiros => 'Balinheiros',
            self::Copos => 'Copos',
            self::Alimentacao => 'Alimentação',
            self::Patrocinio => 'Patrocínio',
            self::Outros => 'Outros',
        };
    }

    public function spreadsheetLabel(): string
    {
        return mb_strtoupper($this->label());
    }

    /**
     * Linhas semeadas ao criar a planilha — as mesmas do arquivo, na mesma ordem, menos
     * `Patrocinio` (adicionado sob demanda, um por patrocinador) e `Outros`.
     *
     * @return array<self>
     */
    public static function seedRows(): array
    {
        return [
            self::IngressosTicketeira,
            self::LoungesTicketeira,
            self::LoungesAvulso,
            self::Estacionamento,
            self::BarEmpresa,
            self::BarDrinks,
            self::Balinheiros,
            self::Copos,
            self::Alimentacao,
        ];
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
