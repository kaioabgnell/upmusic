<?php

namespace App\Http\Controllers\Rentals;

use App\Actions\Rentals\RegisterRentalReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rentals\StoreRentalReceiptRequest;
use App\Models\RentalOrder;
use App\Models\RentalReceipt;
use App\Support\RentalPresenter;

/**
 * Recebimentos do pedido (specs/24 §4.4). Continuam abertos mesmo com o pedido
 * Encerrado/Cancelado (specs/24 §6/§17) — por isso não estende `RentalController::authorizeWrite()`.
 */
class RentalReceiptController extends Controller
{
    public function store(StoreRentalReceiptRequest $request, RentalOrder $pedido, RegisterRentalReceipt $action)
    {
        $this->authorize('update', $pedido);

        $action->store($pedido, $request->validated(), $request->user());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())], 201);
    }

    public function destroy(RentalReceipt $recebimento, RegisterRentalReceipt $action)
    {
        $pedido = $recebimento->order;
        $this->authorize('update', $pedido);

        $action->destroy($recebimento);

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())]);
    }
}
