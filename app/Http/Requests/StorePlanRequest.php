<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota já restrita a admin/coordenador
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'empresa_id' => 'empresa', 'period_year' => 'ano', 'period_month' => 'mês'];
    }
}
