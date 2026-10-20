<?php

namespace App\Http\Requests\Rentals;

/** Edição de uma linha já existente — `rental_item_id` não muda (specs/24 §4.3: snapshot na linha). */
class UpdateRentalOrderLineRequest extends StoreRentalOrderLineRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['rental_item_id']);
        $rules['quantity'] = ['sometimes', 'integer', 'min:1'];

        return $rules;
    }
}
