<?php

namespace App\Http\Requests\Rentals;

use Illuminate\Foundation\Http\FormRequest;

/** Cancelamento do pedido (specs/24 §6): motivo obrigatório. */
class CancelRentalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::cancel)
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public function attributes(): array
    {
        return ['reason' => 'motivo'];
    }
}
