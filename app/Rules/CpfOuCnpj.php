<?php

namespace App\Rules;

use App\Support\Br;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aceita CPF ou CNPJ, sem exigir que o documento combine com o tipo de pessoa (PF/PJ) do
 * cadastro — há fornecedor cadastrado como PJ cujo pagamento vai direto para uma pessoa física
 * (e vice-versa), então o campo "Tipo" classifica o fornecedor mas não trava o formato do
 * documento.
 */
class CpfOuCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Br::isValidCpf($value) && ! Br::isValidCnpj($value)) {
            $fail('O :attribute informado não é um CPF nem um CNPJ válido.');
        }
    }
}
