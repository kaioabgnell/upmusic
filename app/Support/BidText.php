<?php

namespace App\Support;

/**
 * Normalização de texto compartilhada pelo módulo de Licitações (ver specs/21 §10.3).
 * Fonte única da verdade: matcher, apelidos do catálogo e afinidade de ramo usam as mesmas regras.
 */
class BidText
{
    /** Palavras sem valor discriminante em nomes de certidão. */
    private const STOPWORDS = [
        'certidao', 'certidoes', 'negativa', 'negativas', 'positiva', 'debitos', 'debito',
        'de', 'da', 'do', 'das', 'dos', 'e', 'ou', 'a', 'o', 'as', 'os', 'em', 'com', 'para',
        'relativos', 'relativo', 'prova', 'comprovante', 'documento', 'regularidade',
    ];

    /** Minúsculas, sem acento, sem pontuação, espaços colapsados. */
    public static function normalize(?string $value): string
    {
        $text = mb_strtolower(trim((string) $value), 'UTF-8');

        // Remove diacríticos (NFD + strip). iconv cobre o caso comum sem depender do intl.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }

        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** Tokens relevantes (sem stopwords e sem tokens de 1 caractere). */
    public static function tokens(?string $value): array
    {
        $tokens = array_filter(
            explode(' ', self::normalize($value)),
            fn (string $t) => mb_strlen($t) > 1 && ! in_array($t, self::STOPWORDS, true)
        );

        return array_values(array_unique($tokens));
    }

    /** Similaridade de Jaccard entre dois nomes (0..1). */
    public static function similarity(?string $a, ?string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /** Só os dígitos (CNPJ, CNAE, códigos). */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    /**
     * CNAE comparável: 7 dígitos ("8121-4/00" -> "8121400"). Comparação usa os 5 primeiros
     * dígitos (classe), que é o nível em que os editais costumam exigir compatibilidade.
     */
    public static function cnaeClass(?string $value): ?string
    {
        $digits = self::digits($value);

        return strlen($digits) >= 5 ? substr($digits, 0, 5) : null;
    }
}
