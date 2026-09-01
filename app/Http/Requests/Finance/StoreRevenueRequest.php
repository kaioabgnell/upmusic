<?php

namespace App\Http\Requests\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Linha da aba RECEITAS (specs/23 §8.3). */
class StoreRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_value' => Br::money($this->input('estimated_value')) ?? 0,
            'actual_value' => Br::money($this->input('actual_value')) ?? 0,
            'received_value' => Br::money($this->input('received_value')) ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_column(FinanceRevenueCategory::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:180'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'estimated_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'actual_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'received_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'finance_payment_source_id' => ['nullable', 'exists:finance_payment_sources,id'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'receita',
            'estimated_value' => 'valor previsto',
            'actual_value' => 'valor realizado',
            'received_value' => 'recebido',
        ];
    }
}
