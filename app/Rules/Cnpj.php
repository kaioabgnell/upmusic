<?php

namespace App\Rules;

use App\Support\Br;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Br::isValidCnpj($value)) {
            $fail('O :attribute informado não é um CNPJ válido.');
        }
    }
}
