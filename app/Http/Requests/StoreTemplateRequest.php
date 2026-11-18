<?php

namespace App\Http\Requests;

use App\Models\CardTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Quadro de custos fixos não recebe template (specs/27 §1.3).
            'board_id' => ['required', Rule::exists('boards', 'id')->where('fixed_costs', false)],
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
