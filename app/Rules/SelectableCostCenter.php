<?php

namespace App\Rules;

use App\Models\CostCenter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Centro de custo escolhível numa linha (specs/25): precisa estar ativo e não excluído — exceto se
 * for o mesmo que a linha já tem, porque o autosave reenvia a linha inteira e um centro que foi
 * inativado depois continua válido onde já estava.
 */
class SelectableCostCenter implements ValidationRule
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

        if (! CostCenter::active()->whereKey($value)->exists()) {
            $fail('Escolha um centro de custo ativo.');
        }
    }
}
