<?php

namespace App\Http\Controllers\Rentals;

use App\Actions\Rentals\RegisterRentalCostPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rentals\StoreRentalCostPaymentRequest;
use App\Models\RentalCostPayment;
use App\Models\RentalOrderCost;
use App\Support\RentalPresenter;

/**
 * Pagamentos de um custo do pedido (specs/24 §4.6). Continuam abertos mesmo com o pedido
 * Encerrado/Cancelado (specs/24 §6/§17) — por isso não estende `RentalController::authorizeWrite()`.
 */
class RentalCostPaymentController extends Controller
{
    public function store(StoreRentalCostPaymentRequest $request, RentalOrderCost $custo, RegisterRentalCostPayment $action)
    {
        $order = $custo->order;
        $this->authorize('update', $order);

        $action->store($custo, $request->validated(), $request->user());

        return response()->json(['order' => RentalPresenter::orderDetail($order->refresh())], 201);
    }

    public function destroy(RentalCostPayment $pagamento, RegisterRentalCostPayment $action)
    {
        $order = $pagamento->cost->order;
        $this->authorize('update', $order);

        $action->destroy($pagamento);

        return response()->json(['order' => RentalPresenter::orderDetail($order->refresh())]);
    }
}
