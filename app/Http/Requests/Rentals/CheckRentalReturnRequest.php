<?php

namespace App\Http\Requests\Rentals;

use Illuminate\Foundation\Http\FormRequest;

/** Conferência de devolução (specs/24 §9 rentals.orders.return): quantidade devolvida por linha. */
class CheckRentalReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::update)
    }

    public function rules(): array
    {
        return [
            'returns' => ['nullable', 'array'],
            'returns.*' => ['integer', 'min:0'],
        ];
    }
}
