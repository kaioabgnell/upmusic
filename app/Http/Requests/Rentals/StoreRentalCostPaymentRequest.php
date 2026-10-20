<?php

namespace App\Http\Requests\Rentals;

use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Pagamento parcial de um custo do pedido (specs/24 §4.6). */
class StoreRentalCostPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::update)
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => Br::money($this->input('amount'))]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'paid_at' => ['required', 'date'],
            'finance_payment_source_id' => ['nullable', 'exists:finance_payment_sources,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'valor',
            'paid_at' => 'data do pagamento',
            'finance_payment_source_id' => 'fonte do pagamento',
        ];
    }
}
