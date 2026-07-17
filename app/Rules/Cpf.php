<?php

namespace App\Rules;

use App\Support\Br;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Br::isValidCpf($value)) {
            $fail('O :attribute informado não é um CPF válido.');
        }
    }
}
