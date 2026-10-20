<?php

namespace App\Services\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalItem;
use App\Models\RentalOrderItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Disponibilidade de inventário por período (specs/24 §7.3). **Só informa, nunca bloqueia**
 * (specs/24 §2 Q13/Q27): o chamador decide o que fazer com um resultado negativo.
 *
 * Uma única query agregada atende todos os itens de uma vez — nunca uma query por item.
 */
class RentalAvailabilityService
{
    /** Quantidade reservada por item num período, por pedidos Confirmada/Em uso que se sobrepõem. */
    public function reservedQuantities(string|CarbonInterface $start, string|CarbonInterface $end, ?int $excludeOrderId = null): Collection
    {
        return RentalOrderItem::query()
            ->selectRaw('rental_order_items.rental_item_id as item_id, SUM(rental_order_items.quantity) as reserved')
            ->join('rental_orders', 'rental_orders.id', '=', 'rental_order_items.rental_order_id')
            ->whereIn('rental_orders.status', [RentalOrderStatus::Confirmada->value, RentalOrderStatus::EmUso->value])
            ->whereNull('rental_orders.deleted_at')
            ->where('rental_orders.start_at', '<', $end)
            ->where('rental_orders.end_at', '>', $start)
            ->when($excludeOrderId, fn ($q) => $q->where('rental_orders.id', '<>', $excludeOrderId))
            ->groupBy('rental_order_items.rental_item_id')
            ->pluck('reserved', 'item_id');
    }

    /**
     * Disponível por item = quantidade total − reservado (exceto o próprio pedido, se informado).
     * Pode retornar negativo — é assim que o chamador detecta a falta (specs/24 §7.3).
     *
     * @param  array<int>  $itemIds
     * @return array<int,int>
     */
    public function availableQuantities(array $itemIds, string|CarbonInterface $start, string|CarbonInterface $end, ?int $excludeOrderId = null): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $reserved = $this->reservedQuantities($start, $end, $excludeOrderId);
        $quantities = RentalItem::whereIn('id', $itemIds)->pluck('quantity', 'id');

        $result = [];
        foreach ($itemIds as $id) {
            $result[$id] = (int) ($quantities[$id] ?? 0) - (int) ($reserved[$id] ?? 0);
        }

        return $result;
    }

    public function availableQuantity(int $itemId, string|CarbonInterface $start, string|CarbonInterface $end, ?int $excludeOrderId = null): int
    {
        return $this->availableQuantities([$itemId], $start, $end, $excludeOrderId)[$itemId] ?? 0;
    }

    /**
     * "Disponível hoje" (specs/24 §8.5), para a lista de Itens: mesmo cálculo com um período
     * instantâneo — só conta pedidos cuja janela [start_at, end_at) contém o instante atual.
     *
     * @param  array<int>  $itemIds
     * @return array<int,int>
     */
    public function availableToday(array $itemIds): array
    {
        $now = now();

        return $this->availableQuantities($itemIds, $now, $now, null);
    }
}
