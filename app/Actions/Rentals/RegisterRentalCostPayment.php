<?php

namespace App\Actions\Rentals;

use App\Models\RentalCostPayment;
use App\Models\RentalOrderCost;
use App\Models\User;

/** Pagamento parcial de um custo do pedido (specs/24 §4.6) — mesmo padrão do Financeiro do Evento. */
class RegisterRentalCostPayment
{
    public function store(RentalOrderCost $cost, array $data, ?User $actor = null): RentalCostPayment
    {
        return $cost->payments()->create([
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'finance_payment_source_id' => $data['finance_payment_source_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    public function destroy(RentalCostPayment $payment): void
    {
        $payment->delete();
    }
}
