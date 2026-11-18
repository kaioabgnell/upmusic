<?php

namespace App\Http\Requests\Finance;

use App\Rules\SelectableBankAccount;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Pagamento de uma linha de custo (specs/23 §4.4). */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => Br::money($this->input('amount'))]);
    }

    public function rules(): array
    {
        return [
            'finance_payment_source_id' => ['required', 'exists:finance_payment_sources,id'],
            // De onde saiu o dinheiro (specs/26): opcional; na edição aceita a conta que o
            // pagamento já tem, mesmo que tenha sido inativada depois.
            'bank_account_id' => ['nullable', 'integer', new SelectableBankAccount($this->route('payment')?->bank_account_id)],
            // Valor sempre positivo: estorno é exclusão do lançamento, não valor negativo — assim
            // o SUM de "PAGO" nunca precisa de sinal e a auditoria continua legível.
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'finance_payment_source_id' => 'grupo de pagamento',
            'bank_account_id' => 'conta bancária',
            'amount' => 'valor',
            'paid_at' => 'data do pagamento',
        ];
    }
}
