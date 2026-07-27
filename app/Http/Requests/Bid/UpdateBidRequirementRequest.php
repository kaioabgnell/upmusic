<?php

namespace App\Http\Requests\Bid;

use App\Domain\Enums\BidRequirementKind;
use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correção ou dispensa de um requisito extraído (ver specs/21 §9.6).
 * Ignorar um requisito é a saída para extração equivocada — sem apagar o rastro.
 */
class UpdateBidRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requirement'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'kind' => ['required', Rule::in(array_column(BidRequirementKind::cases(), 'value'))],
            'bid_document_type_id' => ['nullable', 'exists:bid_document_types,id'],
            'mandatory' => ['boolean'],
            'ignored' => ['boolean'],
            'ignored_reason' => ['nullable', 'required_if:ignored,true', 'string', 'max:255'],
            'expected_numeric_min' => ['nullable', 'numeric', 'min:0'],
            'expected_percent_of_estimate' => ['nullable', 'numeric', 'between:0,100'],
            'expected_cnae' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mandatory' => $this->boolean('mandatory'),
            'ignored' => $this->boolean('ignored'),
            'expected_numeric_min' => Br::money($this->input('expected_numeric_min')),
        ]);
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome do requisito',
            'kind' => 'natureza',
            'ignored_reason' => 'justificativa',
        ];
    }

    public function messages(): array
    {
        return ['ignored_reason.required_if' => 'Explique por que o requisito não se aplica.'];
    }
}
