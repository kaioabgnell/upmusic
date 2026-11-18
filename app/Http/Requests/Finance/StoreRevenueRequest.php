<?php

namespace App\Http\Requests\Finance;

use App\Domain\Enums\FinanceRevenueCategory;
use App\Rules\SelectableBankAccount;
use App\Rules\SelectableCostCenter;
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
            // Mesmo request para criar e atualizar: na atualização, o centro que a linha já tem
            // continua aceito mesmo se foi inativado depois (specs/25).
            'cost_center_id' => ['nullable', 'integer', new SelectableCostCenter($this->route('revenue')?->cost_center_id)],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'estimated_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'actual_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'received_value' => ['numeric', 'min:0', 'max:9999999999999'],
            'finance_payment_source_id' => ['nullable', 'exists:finance_payment_sources,id'],
            // Para onde entrou o dinheiro (specs/26) — mesma regra do inativo que o centro de custo.
            'bank_account_id' => ['nullable', 'integer', new SelectableBankAccount($this->route('revenue')?->bank_account_id)],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'receita',
            'cost_center_id' => 'centro de custo',
            'bank_account_id' => 'conta bancária',
            'estimated_value' => 'valor previsto',
            'actual_value' => 'valor realizado',
            'received_value' => 'recebido',
        ];
    }
}
