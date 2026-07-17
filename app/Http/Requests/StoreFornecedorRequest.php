<?php

namespace App\Http\Requests;

use App\Domain\Enums\PessoaTipo;
use App\Models\Fornecedor;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Fornecedor::class);
    }

    public function rules(): array
    {
        $documentRule = $this->input('type') === PessoaTipo::PF->value ? new Cpf : new Cnpj;

        return [
            'type' => ['required', Rule::in(['PF', 'PJ'])],
            'name' => ['required', 'string', 'max:180'],
            'document' => ['required', 'string', $documentRule, Rule::unique('fornecedores', 'document')->whereNull('deleted_at')],
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
