<?php

namespace App\Http\Requests;

use App\Domain\Enums\UnidadeMedida;
use App\Models\FornecedorCategoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFornecedorCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FornecedorCategoria::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120', Rule::unique('fornecedor_categorias', 'nome')->whereNull('deleted_at')],
            'unidade' => ['nullable', Rule::enum(UnidadeMedida::class)],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function attributes(): array
    {
        return ['nome' => 'nome', 'unidade' => 'unidade'];
    }
}
