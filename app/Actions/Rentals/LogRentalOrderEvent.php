<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\RentalOrderLog;
use App\Models\User;

/** Grava uma entrada no histórico do pedido de locação (specs/24 §4.7). */
class LogRentalOrderEvent
{
    public function execute(
        RentalOrder $order,
        RentalOrderLogType $type,
        ?User $actor = null,
        ?string $message = null,
        ?RentalOrderStatus $from = null,
        ?RentalOrderStatus $to = null,
        ?array $payload = null,
    ): RentalOrderLog {
        return $order->logs()->create([
            'user_id' => $actor?->id,
            'type' => $type->value,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'message' => $message,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
