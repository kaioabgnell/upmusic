<?php

namespace App\Http\Requests\Rentals;

use Illuminate\Foundation\Http\FormRequest;

/** Reabertura do pedido encerrado (specs/24 §6) — motivo opcional, registrado no histórico. */
class ReopenRentalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::reopen)
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
