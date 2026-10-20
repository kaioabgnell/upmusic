<?php

namespace App\Http\Requests\Rentals;

use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Linha de item do pedido (specs/24 §4.3 / §8.3) — usada na criação e, por herança, na edição. */
class StoreRentalOrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::update)
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'unit_price' => Br::money($this->input('unit_price')),
        ], fn ($v) => $v !== null));
    }

    public function rules(): array
    {
        return [
            'rental_item_id' => ['required', 'exists:rental_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'daily_count' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rental_item_id' => 'item',
            'quantity' => 'quantidade',
            'daily_count' => 'diárias',
            'unit_price' => 'valor unitário',
        ];
    }
}
