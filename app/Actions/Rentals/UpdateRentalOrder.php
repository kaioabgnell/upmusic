<?php

namespace App\Actions\Rentals;

use App\Models\RentalOrder;

/**
 * Atualiza os dados do pedido (cliente, local, período, desconto, vencimento, observações — aba
 * Dados, specs/24 §8.3). Sempre recalcula os totais (o desconto pode ter mudado) e, se o período
 * mudou, refaz o aviso de disponibilidade em todos os pedidos afetados — não só neste.
 */
class UpdateRentalOrder
{
    public function __construct(
        private RecalculateRentalOrderTotals $recalc,
        private RefreshAvailabilityWarnings $warnings,
    ) {}

    public function execute(RentalOrder $order, array $data): RentalOrder
    {
        $periodChanged = $this->periodChanged($order, $data);

        $order->fill(array_intersect_key($data, array_flip([
            'empresa_id', 'client_name', 'location', 'start_at', 'end_at',
            'discount_type', 'discount_value', 'revenue_due_date', 'notes',
        ])))->save();

        $this->recalc->execute($order);
        $order->refresh();

        if ($order->status->reservesStock()) {
            $periodChanged ? $this->warnings->execute($order) : $this->warnings->refreshOne($order);
        }

        return $order->refresh();
    }

    private function periodChanged(RentalOrder $order, array $data): bool
    {
        $startChanged = array_key_exists('start_at', $data)
            && $data['start_at'] != $order->start_at?->toDateTimeString();
        $endChanged = array_key_exists('end_at', $data)
            && $data['end_at'] != $order->end_at?->toDateTimeString();

        return $startChanged || $endChanged;
    }
}
