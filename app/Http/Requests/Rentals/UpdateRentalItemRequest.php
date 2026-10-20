<?php

namespace App\Http\Requests\Rentals;

use Illuminate\Validation\Rule;

/** Mesmas regras do cadastro, com o `sku` único ignorando o próprio item e `active` editável. */
class UpdateRentalItemRequest extends StoreRentalItemRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('rental_items', 'sku')->ignore($this->route('item'))],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
