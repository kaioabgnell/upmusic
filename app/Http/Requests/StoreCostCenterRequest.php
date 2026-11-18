<?php

namespace App\Http\Requests;

use App\Models\CostCenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CostCenter::class);
    }

    /** Id ignorado na checagem de unicidade (null na criação). */
    protected function ignoreId(): ?int
    {
        return null;
    }

    public function rules(): array
    {
        // Únicos só entre os não excluídos: um centro na lixeira libera código e nome.
        $unique = fn (string $column) => Rule::unique('cost_centers', $column)
            ->ignore($this->ignoreId())
            ->whereNull('deleted_at');

        return [
            'code' => ['nullable', 'string', 'max:30', $unique('code')],
            'name' => ['required', 'string', 'max:120', $unique('name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => filled($this->input('code')) ? trim((string) $this->input('code')) : null,
            'active' => $this->boolean('active'),
        ]);
    }

    public function attributes(): array
    {
        return ['code' => 'código', 'name' => 'nome', 'description' => 'descrição'];
    }
}
