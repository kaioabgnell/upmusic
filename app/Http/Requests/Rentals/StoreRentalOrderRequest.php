<?php

namespace App\Http\Requests\Rentals;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação do pedido de locação (specs/24 §2 Q8): em Orçamento, cliente e período podem ficar vazios
 * — a exigência só vale a partir de Confirmada (checada no gate de `MoveRentalOrder`).
 */
class StoreRentalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::create)
    }

    public function rules(): array
    {
        return [
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'client_name' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'cliente',
            'client_name' => 'cliente',
            'start_at' => 'retirada',
            'end_at' => 'devolução',
        ];
    }
}
