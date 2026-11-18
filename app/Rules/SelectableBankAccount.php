<?php

namespace App\Rules;

use App\Models\BankAccount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Conta bancária escolhível num lançamento (specs/26): precisa estar ativa e não excluída —
 * exceto se for a mesma que o lançamento já tem, porque o autosave reenvia a linha inteira e uma
 * conta inativada depois continua válida onde já estava.
 */
class SelectableBankAccount implements ValidationRule
{
    public function __construct(private ?int $currentId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->currentId !== null && (int) $value === $this->currentId) {
            return;
        }

        if (! BankAccount::active()->whereKey($value)->exists()) {
            $fail('Escolha uma conta bancária ativa.');
        }
    }
}
