<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Cancela o pedido (specs/24 §6/§2.1), de qualquer etapa exceto Encerrada. Fica só leitura, exceto
 * recebimentos e pagamentos (sinal retido, multa). Libera a reserva de estoque.
 */
class CancelRentalOrder
{
    public function __construct(private LogRentalOrderEvent $logger, private RefreshAvailabilityWarnings $warnings) {}

    public function execute(RentalOrder $order, string $reason, User $actor): RentalOrder
    {
        if ($order->status === RentalOrderStatus::Encerrada) {
            throw ValidationException::withMessages(['status' => 'Um pedido encerrado não pode ser cancelado.']);
        }
        if ($order->status === RentalOrderStatus::Cancelada) {
            throw ValidationException::withMessages(['status' => 'Pedido já está cancelado.']);
        }

        $from = $order->status;

        $order->update([
            'status' => RentalOrderStatus::Cancelada->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'has_availability_warning' => false,
        ]);

        $this->logger->execute($order, RentalOrderLogType::Cancelled, $actor, $reason, $from, RentalOrderStatus::Cancelada);

        // Libera a reserva: outros pedidos que disputavam o mesmo item no período podem deixar de
        // ter aviso de falta.
        $this->warnings->execute($order->refresh());

        return $order->refresh();
    }
}
