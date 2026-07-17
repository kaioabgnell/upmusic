<?php

namespace App\Http\Requests;

use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Board::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'setor_id' => ['nullable', 'exists:setores,id'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'icon' => ['nullable', 'string', 'max:40'],
            'active' => ['boolean'],
            'with_default_columns' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'with_default_columns' => $this->boolean('with_default_columns'),
        ]);
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'setor_id' => 'setor', 'description' => 'descrição', 'color' => 'cor', 'icon' => 'ícone'];
    }
}
