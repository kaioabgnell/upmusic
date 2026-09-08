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
        return ['type' => 'tipo', 'name' => 'nome', 'document' => 'documento', 'fornecedor_categoria_id' => 'categoria'];
    }
}
