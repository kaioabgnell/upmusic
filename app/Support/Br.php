<?php

namespace App\Support;

/**
 * Helpers de documentos e formatação brasileiros (CPF, CNPJ).
 */
class Br
{
    /** Remove tudo que não é dígito. */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    public static function isValidCpf(?string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(?string $value): bool
    {
        $cnpj = self::digits($value);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([[12, $weights1], [13, $weights2]] as [$len, $weights]) {
            $sum = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$i];
            }
            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;
            if ((int) $cnpj[$len] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function formatCpf(string $value): string
    {
        $c = self::digits($value);

        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $c);
    }

    public static function formatCnpj(string $value): string
    {
        $c = self::digits($value);

        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $c);
    }

    /**
     * Converte valor monetário em formato BR ("1.234,56") ou numérico para float.
     * Retorna null se vazio.
     */
    public static function money(string|float|int|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^\d,.-]/', '', (string) $value);
        // Remove separador de milhar (.) e troca vírgula decimal por ponto.
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }
}
