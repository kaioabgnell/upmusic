<?php

namespace App\Actions\Rentals;

use App\Models\RentalOrder;
use App\Models\RentalReceipt;
use App\Models\User;

/** Recebimento parcial da receita do pedido (specs/24 §4.4) — mesmo padrão do Financeiro do Evento. */
class RegisterRentalReceipt
{
    public function store(RentalOrder $order, array $data, ?User $actor = null): RentalReceipt
    {
        return $order->receipts()->create([
            'amount' => $data['amount'],
            'received_at' => $data['received_at'],
            'finance_payment_source_id' => $data['finance_payment_source_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    public function destroy(RentalReceipt $receipt): void
    {
        $receipt->delete();
    }
}
