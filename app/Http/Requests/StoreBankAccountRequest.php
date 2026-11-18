<?php

namespace App\Http\Requests;

use App\Domain\Enums\BankAccountType;
use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BankAccount::class);
    }

    /** Id ignorado na checagem de unicidade (null na criação). */
    protected function ignoreId(): ?int
    {
        return null;
    }

    public function rules(): array
    {
        return [
            'bank' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'agency' => ['required', 'string', 'max:20'],
            // Banco + agência + conta identificam a conta: únicos só entre as não excluídas.
            'account_number' => ['required', 'string', 'max:30',
                Rule::unique('bank_accounts', 'account_number')
                    ->ignore($this->ignoreId())
                    ->where('bank', $this->input('bank'))
                    ->where('agency', $this->input('agency'))
                    ->whereNull('deleted_at'),
            ],
            'account_type' => ['required', Rule::enum(BankAccountType::class)],
            'active' => ['boolean'],
            'empresas' => ['required', 'array', 'min:1'],
            'empresas.*' => ['integer', 'distinct', Rule::exists('empresas', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (string $key) => filled($this->input($key)) ? trim((string) $this->input($key)) : null;

        $this->merge([
            'bank' => $trim('bank'),
            'name' => $trim('name'),
            'agency' => $trim('agency'),
            'account_number' => $trim('account_number'),
            'active' => $this->boolean('active'),
            'empresas' => array_values(array_filter((array) $this->input('empresas', []))),
        ]);
    }

    public function messages(): array
    {
        return [
            'account_number.unique' => 'Já existe uma conta com este banco, agência e número.',
            'empresas.required' => 'Selecione pelo menos uma empresa.',
            'empresas.min' => 'Selecione pelo menos uma empresa.',
        ];
    }

    public function attributes(): array
    {
        return [
            'bank' => 'banco',
            'name' => 'nome da conta',
            'agency' => 'agência',
            'account_number' => 'conta',
            'account_type' => 'tipo de conta',
            'empresas' => 'empresas',
        ];
    }
}
