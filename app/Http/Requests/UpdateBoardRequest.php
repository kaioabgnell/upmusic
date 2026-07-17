<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'setor_id' => 'setor', 'description' => 'descrição', 'color' => 'cor', 'icon' => 'ícone'];
    }
}
