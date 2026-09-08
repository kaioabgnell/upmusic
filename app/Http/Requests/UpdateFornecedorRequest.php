<?php

namespace App\Http\Requests;

use App\Rules\CpfOuCnpj;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fornecedor'));
    }

    public function rules(): array
    {
        $id = $this->route('fornecedor')->id;

        return [
            'type' => ['required', Rule::in(['PF', 'PJ'])],
            'name' => ['required', 'string', 'max:180'],
            // Aceita CPF ou CNPJ independente do "Tipo": há fornecedor PJ pago direto à pessoa
            // física (e vice-versa) — ver App\Rules\CpfOuCnpj.
            'document' => ['required', 'string', new CpfOuCnpj, Rule::unique('fornecedores', 'document')->ignore($id)->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            // Dados bancários: todos opcionais (nem todo fornecedor recebe por repasse bancário) e
            // sem validação de formato — agência/conta variam por banco, e a chave PIX pode ser
            // CPF/CNPJ, e-mail, telefone ou aleatória.
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_agency' => ['nullable', 'string', 'max:20'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'pix_key' => ['nullable', 'string', 'max:150'],
            'fornecedor_categoria_id' => ['nullable', 'exists:fornecedor_categorias,id'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document' => Br::digits($this->document),
            'active' => $this->boolean('active'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo', 'name' => 'nome', 'document' => 'documento', 'fornecedor_categoria_id' => 'categoria',
            'bank_name' => 'banco', 'bank_agency' => 'agência', 'bank_account' => 'conta', 'pix_key' => 'chave PIX',
        ];
    }
}
