<?php

namespace App\Http\Requests\Rentals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mover o pedido no Kanban (specs/24 §9 rentals.orders.move). Encerrada e Cancelada têm rotas
 * dedicadas (fechar/cancelar) — não são destinos válidos por aqui.
 */
class MoveRentalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::update)
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['orcamento', 'confirmada', 'em_uso', 'devolvida'])],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
