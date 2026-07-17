<?php

namespace App\Http\Requests;

use App\Domain\Enums\UnidadeMedida;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFornecedorCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fornecedorCategoria'));
    }

    public function rules(): array
    {
        $id = $this->route('fornecedorCategoria')->id;

        return [
            'nome' => ['required', 'string', 'max:120', Rule::unique('fornecedor_categorias', 'nome')->ignore($id)->whereNull('deleted_at')],
            'unidade' => ['nullable', Rule::enum(UnidadeMedida::class)],
            'preco_interno' => ['nullable', 'numeric'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'preco_interno' => Br::money($this->input('preco_interno')),
        ]);
    }

    public function attributes(): array
    {
        return ['nome' => 'nome', 'unidade' => 'unidade'];
    }
}
