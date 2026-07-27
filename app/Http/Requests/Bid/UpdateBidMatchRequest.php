<?php

namespace App\Http\Requests\Bid;

use App\Domain\Enums\BidMatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Override manual de uma conferência (ver specs/21 §9.6): vincular outro documento, marcar como
 * atendido ou como não aplicável. A decisão humana sobrevive a todo recálculo.
 */
class UpdateBidMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('match'));
    }

    public function rules(): array
    {
        $company = $this->route('match')->bid_company_id;

        return [
            'status' => ['required', Rule::in(array_column(BidMatchStatus::cases(), 'value'))],
            // Só documentos vigentes da MESMA empresa podem ser vinculados.
            'bid_document_id' => [
                'nullable',
                Rule::exists('bid_documents', 'id')
                    ->where('bid_company_id', $company)
                    ->whereNull('superseded_at')
                    ->whereNull('deleted_at'),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'situação', 'bid_document_id' => 'documento', 'reason' => 'observação'];
    }

    public function messages(): array
    {
        return ['bid_document_id.exists' => 'Selecione um documento vigente desta mesma empresa.'];
    }
}
