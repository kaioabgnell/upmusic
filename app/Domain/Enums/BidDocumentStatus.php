<?php

namespace App\Domain\Enums;

/**
 * Status de vigência de um documento de habilitação (ver specs/21 §10.1).
 * Nunca é gravado em coluna — é sempre derivado de `expires_at`/`no_expiry` na leitura.
 */
enum BidDocumentStatus: string
{
    case Valido = 'valido';
    case Vencendo = 'vencendo';
    case Vencido = 'vencido';
    case Permanente = 'permanente';

    public function label(): string
    {
        return match ($this) {
            self::Valido => 'Válido',
            self::Vencendo => 'Vencendo',
            self::Vencido => 'Vencido',
            self::Permanente => 'Sem validade',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Valido => 'fa-circle-check',
            self::Vencendo => 'fa-clock',
            self::Vencido => 'fa-circle-xmark',
            self::Permanente => 'fa-infinity',
        };
    }

    /** Classes Tailwind do badge (paleta semântica de specs/21 §9). */
    public function classes(): string
    {
        return match ($this) {
            self::Valido => 'bg-green-100 text-green-700',
            self::Vencendo => 'bg-amber-100 text-amber-700',
            self::Vencido => 'bg-red-100 text-red-700',
            self::Permanente => 'bg-gray-100 text-gray-700',
        };
    }

    /** Conta como "em ordem" para fins de habilitação (vencendo ainda vale hoje). */
    public function isUsable(): bool
    {
        return $this !== self::Vencido;
    }
}
