<?php

namespace App\Http\Requests\Rentals;

use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Cadastro de item do inventário (specs/24 §4.1 / §9): nome, quantidade e valor do produto obrigatórios. */
class StoreRentalItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalItemPolicy::create)
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'unit_cost' => Br::money($this->input('unit_cost')),
            'rental_price' => Br::money($this->input('rental_price')),
        ], fn ($v) => $v !== null));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:rental_items,sku'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'purchase_date' => ['nullable', 'date'],
            'rental_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'sku' => 'código',
            'quantity' => 'quantidade',
            'unit_cost' => 'valor do produto',
            'purchase_date' => 'data de compra',
            'rental_price' => 'valor de locação',
        ];
    }
}
