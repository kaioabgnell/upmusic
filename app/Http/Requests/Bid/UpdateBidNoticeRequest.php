<?php

namespace App\Http\Requests\Bid;

use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Correção manual dos metadados do edital extraídos pela IA (ver specs/21 §9.6). */
class UpdateBidNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('notice'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'agency' => ['nullable', 'string', 'max:180'],
            'number' => ['nullable', 'string', 'max:60'],
            'process_number' => ['nullable', 'string', 'max:60'],
            'modality' => ['nullable', 'string', 'max:60'],
            'portal' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'object_summary' => ['nullable', 'string', 'max:2000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'session_at' => ['nullable', 'date'],
            'proposal_deadline_at' => ['nullable', 'date'],
            'me_epp_exclusive' => ['boolean'],
            'requires_site_visit' => ['boolean'],
            'requires_bid_bond' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estimated_value' => Br::money($this->input('estimated_value')),
            'uf' => $this->uf ? strtoupper($this->uf) : null,
            'me_epp_exclusive' => $this->boolean('me_epp_exclusive'),
            'requires_site_visit' => $this->boolean('requires_site_visit'),
            'requires_bid_bond' => $this->boolean('requires_bid_bond'),
        ]);
    }

    public function attributes(): array
    {
        return ['title' => 'título', 'agency' => 'órgão', 'estimated_value' => 'valor estimado'];
    }
}
