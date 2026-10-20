<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalOrder;
use App\Models\User;

/** Cria o pedido de locação em Orçamento e gera o código sequencial LOC-0001 (specs/24 §2 Q26). */
class CreateRentalOrder
{
    public function __construct(private LogRentalOrderEvent $logger) {}

    public function execute(array $data, User $actor): RentalOrder
    {
        $order = RentalOrder::create([
            'status' => RentalOrderStatus::Orcamento->value,
            'empresa_id' => $data['empresa_id'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'location' => $data['location'] ?? null,
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        // Código legível (specs/24 §2 Q26): não dá para gerar antes do insert (precisa do id
        // autoincrement), então o update acontece logo em seguida, ainda na mesma requisição.
        $order->update(['code' => sprintf('LOC-%04d', $order->id)]);

        $this->logger->execute($order, RentalOrderLogType::Created, $actor, 'Pedido criado.');

        return $order->refresh();
    }
}
