<?php

namespace App\Domain\Enums;

/** Natureza do grupo de pagamento (specs/23 §4.2) — espelha as colunas O-S da planilha. */
enum FinancePaymentSourceKind: string
{
    case Caixa = 'caixa';
    case Socio = 'socio';
    case Ticketeira = 'ticketeira';
    case Bar = 'bar';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Caixa => 'Caixa do evento',
            self::Socio => 'Sócio',
            self::Ticketeira => 'Ticketeira',
            self::Bar => 'Bar',
            self::Outro => 'Outro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Caixa => 'fa-cash-register',
            self::Socio => 'fa-user-tie',
            self::Ticketeira => 'fa-ticket',
            self::Bar => 'fa-martini-glass',
            self::Outro => 'fa-wallet',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
