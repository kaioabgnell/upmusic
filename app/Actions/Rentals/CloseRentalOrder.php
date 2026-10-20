<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Encerra o pedido (specs/24 §6/§17): exige receita realizada e realizado em todos os custos
 * (0 é aceito). O pedido trava, mas recebimentos e pagamentos continuam abertos.
 */
class CloseRentalOrder
{
    public function __construct(private LogRentalOrderEvent $logger) {}

    /** @param  array{revenue_actual?: float, costs?: array<int,float>}  $data */
    public function execute(RentalOrder $order, array $data, User $actor): RentalOrder
    {
        if ($order->status->isLocked()) {
            throw ValidationException::withMessages(['status' => 'Pedido já está encerrado ou cancelado.']);
        }

        $order->loadMissing('costs');

        $revenueActual = $data['revenue_actual'] ?? $order->revenue_actual;
        if ($revenueActual === null) {
            throw ValidationException::withMessages(['revenue_actual' => 'Informe a receita realizada para encerrar o pedido.']);
        }

        $costActuals = $data['costs'] ?? [];
        foreach ($order->costs as $cost) {
            $value = array_key_exists($cost->id, $costActuals) ? $costActuals[$cost->id] : $cost->actual_value;
            if ($value === null) {
                throw ValidationException::withMessages(['costs' => 'Informe o valor realizado de todos os custos antes de encerrar.']);
            }
        }

        foreach ($order->costs as $cost) {
            $value = array_key_exists($cost->id, $costActuals) ? $costActuals[$cost->id] : $cost->actual_value;
            if ((float) $value !== (float) ($cost->actual_value ?? -1)) {
                $cost->update(['actual_value' => $value]);
            }
        }

        $from = $order->status;

        $order->update([
            'status' => RentalOrderStatus::Encerrada->value,
            'revenue_actual' => $revenueActual,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        $this->logger->execute($order, RentalOrderLogType::Closed, $actor, 'Pedido encerrado.', $from, RentalOrderStatus::Encerrada);

        return $order->refresh();
    }
}
