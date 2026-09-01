<?php

namespace App\Domain\Enums;

/**
 * Coluna ART da aba CUSTOS (data validation em D8:D183). No áudio o cliente chama de "RT";
 * o arquivo usa ART (Anotação de Responsabilidade Técnica) e o sistema segue o arquivo.
 */
enum FinanceArtStatus: string
{
    case AguardandoEnvio = 'aguardando_envio';
    case NaoTem = 'nao_tem';
    case Ok = 'art_ok';

    public function label(): string
    {
        return match ($this) {
            self::AguardandoEnvio => 'Aguardando envio',
            self::NaoTem => 'Não tem',
            self::Ok => 'ART OK',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::AguardandoEnvio => 'orange',
            self::NaoTem => 'neutral',
            self::Ok => 'success',
        };
    }

    public function spreadsheetLabel(): string
    {
        return match ($this) {
            self::AguardandoEnvio => 'AGUARDANDO ENVIO',
            self::NaoTem => 'NÃO TEM',
            self::Ok => 'ART OK',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
