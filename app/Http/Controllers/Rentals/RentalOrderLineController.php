<?php

namespace App\Http\Controllers\Rentals;

use App\Actions\Rentals\SaveRentalOrderLine;
use App\Http\Requests\Rentals\StoreRentalOrderLineRequest;
use App\Http\Requests\Rentals\UpdateRentalOrderLineRequest;
use App\Models\RentalOrder;
use App\Models\RentalOrderItem;
use App\Support\RentalPresenter;

/** Linhas de item do pedido de locação (specs/24 §4.3 / §8.3). */
class RentalOrderLineController extends RentalController
{
    public function store(StoreRentalOrderLineRequest $request, RentalOrder $pedido, SaveRentalOrderLine $action)
    {
        $this->authorizeWrite($pedido);

        $action->store($pedido, $request->validated());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())], 201);
    }

    public function update(UpdateRentalOrderLineRequest $request, RentalOrderItem $linha, SaveRentalOrderLine $action)
    {
        $pedido = $linha->order;
        $this->authorizeWrite($pedido);

        $action->update($linha, $request->validated());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())]);
    }

    public function destroy(RentalOrderItem $linha, SaveRentalOrderLine $action)
    {
        $pedido = $linha->order;
        $this->authorizeWrite($pedido);

        $action->destroy($linha);

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())]);
    }

    /** Recalcula as diárias de todas as linhas pelo período atual (specs/24 §2.1) — ação confirmada no front. */
    public function recalculateDailyCounts(RentalOrder $pedido, SaveRentalOrderLine $action)
    {
        $this->authorizeWrite($pedido);

        $pedido = $action->recalculateAllDailyCounts($pedido);

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }
}
