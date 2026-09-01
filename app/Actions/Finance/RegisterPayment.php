<?php

namespace App\Actions\Finance;

use App\Models\FinanceCostItem;
use App\Models\FinancePayment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Registra um pagamento numa linha de custo (specs/23 §4.4). O "pago a maior" é permitido de
 * propósito (acontece, e esconder o erro é pior), mas a UI confirma antes.
 */
class RegisterPayment
{
    public function execute(FinanceCostItem $item, array $data, ?User $actor = null): FinancePayment
    {
        if ($item->loadMissing('sheet')->sheet->isClosed()) {
            throw ValidationException::withMessages([
                'finance' => 'A prestação de contas deste evento está fechada.',
            ]);
        }

        return $item->payments()->create([
            'finance_payment_source_id' => $data['finance_payment_source_id'],
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }
}
