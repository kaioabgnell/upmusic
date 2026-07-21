<?php

namespace App\Http\Requests;

use App\Models\Board;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('capture'));
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['orcamento', 'nota_fiscal'])],
            'board_id' => ['required', 'exists:boards,id', function ($attribute, $value, $fail) {
                $board = Board::find($value);
                if (! $board || ! $this->user()->canAccessBoard($board)) {
                    $fail('Você não tem acesso a este quadro.');
                }
            }],
            'title' => ['nullable', 'string', 'max:180'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'estimated_value' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_value' => Br::money($this->input('estimated_value')),
        ]);
    }

    public function attributes(): array
    {
        return [
            'kind' => 'tipo',
            'board_id' => 'quadro',
            'title' => 'título',
            'empresa_id' => 'empresa',
            'fornecedor_id' => 'fornecedor',
            'event_id' => 'evento',
            'estimated_value' => 'valor',
        ];
    }
}
