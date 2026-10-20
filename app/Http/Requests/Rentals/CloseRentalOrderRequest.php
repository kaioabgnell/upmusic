<?php

namespace App\Http\Requests\Rentals;

use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Encerramento do pedido (specs/24 §6): receita realizada e realizado de cada custo (0 é aceito). */
class CloseRentalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::close)
    }

    protected function prepareForValidation(): void
    {
        $costs = collect($this->input('costs', []))
            ->map(fn ($v) => Br::money($v))
            ->filter(fn ($v) => $v !== null)
            ->all();

        $this->merge(array_filter([
            'revenue_actual' => Br::money($this->input('revenue_actual')),
        ], fn ($v) => $v !== null) + ['costs' => $costs]);
    }

    public function rules(): array
    {
        return [
            'revenue_actual' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'costs' => ['nullable', 'array'],
            'costs.*' => ['numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function attributes(): array
    {
        return ['revenue_actual' => 'receita realizada'];
    }
}
