<?php

namespace App\Http\Requests\Rentals;

use App\Domain\Enums\RentalDiscountType;
use App\Support\Br;
use Illuminate\Validation\Rule;

/** Atualização da aba Dados do pedido (specs/24 §8.3): cliente, local, período, desconto e vencimento. */
class UpdateRentalOrderRequest extends StoreRentalOrderRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'discount_value' => Br::money($this->input('discount_value')),
        ], fn ($v) => $v !== null));
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'discount_type' => ['nullable', Rule::in(array_column(RentalDiscountType::cases(), 'value'))],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'revenue_due_date' => ['nullable', 'date'],
        ]);
    }
}
