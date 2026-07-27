<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Saneamento de tudo que vem da IA (ver specs/21 §11 e §12).
 *
 * Premissa: a saída do modelo é entrada não confiável. Todo campo passa por aqui antes de chegar
 * ao banco ou à tela — HTML removido, tamanho limitado, datas e números validados.
 */
class BidSanitizer
{
    public static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $clean = strip_tags((string) $value);
        $clean = preg_replace('/\s+/u', ' ', $clean);
        $clean = trim(html_entity_decode($clean, ENT_QUOTES, 'UTF-8'));

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }

    /** Data em Y-m-d, ou null se inválida/absurda. */
    public static function date(mixed $value): ?string
    {
        $parsed = self::parse($value);

        return $parsed?->toDateString();
    }

    /** Data e hora em Y-m-d H:i:s, ou null. */
    public static function dateTime(mixed $value): ?string
    {
        $parsed = self::parse($value);

        return $parsed?->toDateTimeString();
    }

    /** Número >= 0, ou null (valores negativos vindos da IA são descartados). */
    public static function amount(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = Br::money($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= 0 ? round($number, 2) : null;
    }

    public static function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'sim'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'nao', 'não'], true)) {
            return false;
        }

        return null;
    }

    /** Confiança normalizada em 0..1, ou null. */
    public static function confidence(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(0, min(1, (float) $value)), 3);
    }

    /** Lista de avisos textuais, no máximo 10. */
    public static function warnings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $warnings = [];

        foreach (array_slice($value, 0, 10) as $item) {
            $clean = self::text($item, 255);
            if ($clean !== null) {
                $warnings[] = $clean;
            }
        }

        return $warnings;
    }

    private static function parse(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse(trim($value));
        } catch (Throwable) {
            return null;
        }

        // Janela sanitária: fora disso é alucinação ou erro de OCR, não data de certidão.
        return ($date->year >= 1990 && $date->year <= 2100) ? $date : null;
    }
}
