<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Reativa um pedido cancelado, de volta para Orçamento (specs/24 §2.1) — exclusivo admin/coordenador. */
class ReactivateRentalOrder
{
    public function __construct(private LogRentalOrderEvent $logger) {}

    public function execute(RentalOrder $order, User $actor): RentalOrder
    {
        if ($order->status !== RentalOrderStatus::Cancelada) {
            throw ValidationException::withMessages(['status' => 'Só é possível reativar um pedido cancelado.']);
        }

        $order->update(['status' => RentalOrderStatus::Orcamento->value, 'cancelled_at' => null, 'cancel_reason' => null]);

        $this->logger->execute(
            $order, RentalOrderLogType::Reactivated, $actor, 'Pedido reativado.',
            RentalOrderStatus::Cancelada, RentalOrderStatus::Orcamento,
        );

        return $order->refresh();
    }
}
