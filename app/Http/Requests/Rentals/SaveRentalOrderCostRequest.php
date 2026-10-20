<?php

namespace App\Http\Requests\Rentals;

use App\Support\Br;
use Illuminate\Foundation\Http\FormRequest;

/** Custo simples do pedido (specs/24 §4.5 / §2 Q21-Q27) — criação e, por herança, edição. */
class SaveRentalOrderCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorização real na policy do controller (RentalOrderPolicy::update)
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'estimated_value' => Br::money($this->input('estimated_value')),
            'actual_value' => Br::money($this->input('actual_value')),
        ], fn ($v) => $v !== null));

        // Vazio é "ainda não realizado" (diferente de 0,00) — mesma regra do Financeiro do Evento.
        if ($this->has('actual_value') && $this->input('actual_value') === '') {
            $this->merge(['actual_value' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'rental_order_item_id' => ['nullable', 'exists:rental_order_items,id'],
            'description' => ['required', 'string', 'max:255'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'actual_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rental_order_item_id' => 'item vinculado',
            'description' => 'descrição',
            'estimated_value' => 'valor previsto',
            'actual_value' => 'valor realizado',
            'due_date' => 'vencimento',
        ];
    }
}
