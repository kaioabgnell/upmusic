<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Reabre um pedido encerrado, de volta para Devolvida (specs/24 §6) — exclusivo admin/coordenador. */
class ReopenRentalOrder
{
    public function __construct(private LogRentalOrderEvent $logger) {}

    public function execute(RentalOrder $order, User $actor, ?string $reason = null): RentalOrder
    {
        if ($order->status !== RentalOrderStatus::Encerrada) {
            throw ValidationException::withMessages(['status' => 'Só é possível reabrir um pedido encerrado.']);
        }

        $order->update(['status' => RentalOrderStatus::Devolvida->value, 'closed_at' => null, 'closed_by' => null]);

        $this->logger->execute(
            $order, RentalOrderLogType::Reopened, $actor, $reason ?: 'Pedido reaberto.',
            RentalOrderStatus::Encerrada, RentalOrderStatus::Devolvida,
        );

        return $order->refresh();
    }
}
