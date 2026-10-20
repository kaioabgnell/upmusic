<?php

namespace App\Http\Controllers\Rentals;

use App\Http\Controllers\Controller;
use App\Models\RentalOrder;

/**
 * Base dos controllers da Gestão de Locações (specs/24 §11).
 *
 * O travamento do pedido Encerrado/Cancelado é um guard explícito, igual ao
 * `Finance\FinanceController::authorizeWrite()` — responde 422 com o motivo real, mesmo para quem
 * tem permissão de escrita. Recebimentos e pagamentos NÃO passam por aqui (specs/24 §6/§17):
 * continuam abertos mesmo com o pedido travado.
 */
abstract class RentalController extends Controller
{
    protected function authorizeWrite(RentalOrder $order): void
    {
        $this->authorize('update', $order);

        abort_if(
            $order->isLocked(),
            422,
            'Este pedido está encerrado ou cancelado. Reabra ou reative antes de editar.'
        );
    }
}
