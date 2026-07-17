<?php

namespace App\Http\Requests;

use App\Models\Setor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSetorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Setor::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120', Rule::unique('setores', 'nome')->whereNull('deleted_at')],
            'descricao' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'icon' => ['nullable', 'string', 'max:40'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function attributes(): array
    {
        return ['nome' => 'nome', 'descricao' => 'descrição', 'color' => 'cor', 'icon' => 'ícone'];
    }
}
