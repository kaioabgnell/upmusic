<?php

namespace App\Http\Requests\Finance;

use App\Domain\Enums\FinanceArtStatus;
use App\Domain\Enums\FinanceCostStatus;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Linha da aba CUSTOS. Os valores chegam no formato BR ("1.234,56") vindos da grade e são
 * normalizados antes da validação — as regras numéricas trabalham sempre com float.
 */
class StoreCostItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // autorização real na policy do controller (FinanceSheetPolicy::update)
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'daily_count' => Br::money($this->input('daily_count')),
            'quantity' => Br::money($this->input('quantity')),
            'unit_estimated_1' => Br::money($this->input('unit_estimated_1')),
            'unit_estimated_2' => Br::money($this->input('unit_estimated_2')),
            'unit_actual' => Br::money($this->input('unit_actual')),
        ], fn ($v) => $v !== null));

        // Campo enviado vazio significa "sem valor", não zero — e a diferença importa
        // (`unit_actual` null = ainda não aconteceu; 0,00 = saiu de graça).
        foreach (['unit_estimated_2', 'unit_actual'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'fornecedor_categoria_id' => ['nullable', 'exists:fornecedor_categorias,id'],
            'description' => ['required', 'string', 'max:180'],
            'status' => ['nullable', Rule::in(array_column(FinanceCostStatus::cases(), 'value'))],
            'art_status' => ['nullable', Rule::in(array_column(FinanceArtStatus::cases(), 'value'))],
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'supplier_name' => ['nullable', 'string', 'max:180'],
            'authorized_by' => ['nullable', 'exists:users,id'],
            'authorized_by_name' => ['nullable', 'string', 'max:120'],
            'daily_count' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'unit_estimated_1' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'unit_estimated_2' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'unit_actual' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'descrição',
            'fornecedor_categoria_id' => 'item',
            'daily_count' => 'diárias',
            'quantity' => 'quantidade',
            'unit_estimated_1' => 'valor unitário previsto',
            'unit_estimated_2' => 'valor unitário previsto 2',
            'unit_actual' => 'valor unitário realizado',
        ];
    }
}
