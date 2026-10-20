<?php

namespace App\Services\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Models\RentalCostPayment;
use App\Models\RentalOrder;
use App\Models\RentalOrderCost;
use App\Models\RentalReceipt;
use Illuminate\Database\Eloquent\Builder;

/**
 * Receita/custo/resultado do pedido de locação (specs/24 §7.2) e os totais agregados usados no
 * rodapé da lista (specs/24 §8.4). O valor do produto / investimento do item **não entra** aqui
 * (specs/24 §2 Q6) — é um número à parte, mostrado na ficha do item.
 */
class RentalOrderSummaryService
{
    public function summary(RentalOrder $order): array
    {
        $order->loadMissing(['receipts', 'costs.payments']);

        $received = (float) $order->receipts->sum('amount');
        $revenueEstimated = (float) $order->revenue_estimated;
        $revenueActual = $order->revenue_actual !== null ? (float) $order->revenue_actual : null;
        $receivable = ($revenueActual ?? $revenueEstimated) - $received;

        $costsEstimated = (float) $order->costs->sum('estimated_value');
        $hasPendingActual = $order->costs->contains(fn ($c) => $c->actual_value === null);
        $costsActualBase = (float) $order->costs->sum(fn ($c) => (float) ($c->actual_value ?? $c->estimated_value));
        $costsPaid = (float) $order->costs->flatMap->payments->sum('amount');
        $costsPending = $costsActualBase - $costsPaid;

        $resultEstimated = $revenueEstimated - $costsEstimated;
        $resultActual = $order->status === RentalOrderStatus::Encerrada && $revenueActual !== null && ! $hasPendingActual
            ? $revenueActual - $costsActualBase
            : null;

        return [
            'revenue' => [
                'estimated' => $revenueEstimated,
                'actual' => $revenueActual,
                'received' => $received,
                'receivable' => $receivable,
            ],
            'costs' => [
                'estimated' => $costsEstimated,
                'actual' => $hasPendingActual ? null : $costsActualBase,
                'has_pending' => $hasPendingActual,
                'paid' => $costsPaid,
                'pending' => $costsPending,
            ],
            'result' => [
                'estimated' => $resultEstimated,
                'actual' => $resultActual,
            ],
        ];
    }

    /**
     * Totais agregados sobre a MESMA query filtrada da lista (specs/24 §8.4) — nunca só a página.
     *
     * `setEagerLoads([])` descarta o `with()` que a tela de listagem usa (empresa, recibos): com
     * `Model::preventAccessingMissingAttributes()` ligado (specs/01, fora de produção), carregar uma
     * relation `BelongsTo` exige a FK no SELECT, e aqui só lemos as 3 colunas da agregação.
     */
    public function listTotals(Builder $filtered): array
    {
        $orders = (clone $filtered)->setEagerLoads([])->get(['id', 'revenue_estimated', 'revenue_actual']);
        $ids = $orders->pluck('id');

        $received = (float) RentalReceipt::whereIn('rental_order_id', $ids)->sum('amount');

        $costs = RentalOrderCost::whereIn('rental_order_id', $ids)->get(['id', 'estimated_value', 'actual_value']);
        $costIds = $costs->pluck('id');
        $paid = (float) RentalCostPayment::whereIn('rental_order_cost_id', $costIds)->sum('amount');

        $revenueEstimated = (float) $orders->sum('revenue_estimated');
        $revenueActual = (float) $orders->sum('revenue_actual');
        $costsEstimated = (float) $costs->sum('estimated_value');
        $costsActualBase = (float) $costs->sum(fn ($c) => (float) ($c->actual_value ?? $c->estimated_value));
        $receivableBase = (float) $orders->sum(fn ($o) => (float) ($o->revenue_actual ?? $o->revenue_estimated));

        return [
            'count' => $orders->count(),
            'revenue_estimated' => $revenueEstimated,
            'revenue_actual' => $revenueActual,
            'received' => $received,
            'receivable' => $receivableBase - $received,
            'costs_estimated' => $costsEstimated,
            'paid' => $paid,
            'pending' => $costsActualBase - $paid,
            'result_estimated' => $revenueEstimated - $costsEstimated,
        ];
    }

    /** Totais por coluna do Kanban (specs/24 §8.2): nº de pedidos e receita prevista. */
    public function columnTotals(Builder $filtered): array
    {
        $orders = (clone $filtered)->setEagerLoads([])->get(['id', 'revenue_estimated']);

        return [
            'count' => $orders->count(),
            'revenue_estimated' => (float) $orders->sum('revenue_estimated'),
        ];
    }
}
