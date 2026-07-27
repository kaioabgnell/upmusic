<?php

namespace App\Http\Requests\Bid;

use Illuminate\Validation\Rule;

/** Edição de empresa licitante — mesmas regras do cadastro, ignorando o próprio CNPJ. */
class UpdateBidCompanyRequest extends StoreBidCompanyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('company'));
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['cnpj'] = [
            'required', 'string', new \App\Rules\Cnpj,
            Rule::unique('bid_companies', 'cnpj')->whereNull('deleted_at')->ignore($this->route('company')->id),
        ];

        return $rules;
    }
}
