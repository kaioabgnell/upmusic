<?php

namespace App\Actions\Rentals;

use App\Domain\Enums\RentalOrderLogType;
use App\Models\RentalOrder;
use App\Models\User;

/**
 * Conferência de devolução (specs/24 §6): registra a quantidade devolvida de cada linha. Sem baixa
 * automática — uma diferença só gera aviso; o ajuste de estoque é manual na ficha do item.
 */
class CheckRentalReturn
{
    public function __construct(private LogRentalOrderEvent $logger) {}

    /**
     * @param  array<int,int>  $returns  rental_order_item_id => quantidade devolvida
     */
    public function execute(RentalOrder $order, array $returns, ?User $actor = null): bool
    {
        $order->loadMissing('items');

        $hasDivergence = false;
        foreach ($order->items as $line) {
            $returned = array_key_exists($line->id, $returns) ? max(0, (int) $returns[$line->id]) : $line->quantity;
            $line->update(['returned_quantity' => $returned]);
            if ($returned < $line->quantity) {
                $hasDivergence = true;
            }
        }

        $order->update(['has_return_divergence' => $hasDivergence]);

        $this->logger->execute(
            $order,
            RentalOrderLogType::ReturnChecked,
            $actor,
            $hasDivergence ? 'Divergência encontrada na conferência de devolução.' : 'Devolução conferida sem divergências.',
            payload: $order->items->map(fn ($l) => [
                'item' => $l->item_name,
                'locado' => $l->quantity,
                'devolvido' => $l->returned_quantity,
            ])->all(),
        );

        return $hasDivergence;
    }

    /** Voltar para antes de Devolvida desfaz a conferência (specs/24 §6). */
    public function clear(RentalOrder $order, ?User $actor = null): void
    {
        $order->items()->update(['returned_quantity' => null]);
        $order->update(['has_return_divergence' => false]);

        $this->logger->execute($order, RentalOrderLogType::ReturnCleared, $actor, 'Conferência de devolução desfeita ao voltar etapa.');
    }
}
