<?php

namespace App\Http\Requests;

use App\Models\CardTemplate;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CardTemplate::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'board_id' => ['required', 'exists:boards,id'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'board_id' => 'quadro', 'description' => 'descrição'];
    }
}
