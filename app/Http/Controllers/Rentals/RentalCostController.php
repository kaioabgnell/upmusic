<?php

namespace App\Http\Controllers\Rentals;

use App\Actions\Rentals\SaveRentalOrderCost;
use App\Http\Requests\Rentals\SaveRentalOrderCostRequest;
use App\Models\RentalOrder;
use App\Models\RentalOrderCost;
use App\Support\RentalPresenter;

/** Custo simples do pedido de locação (specs/24 §4.5 / §2 Q21-Q27). */
class RentalCostController extends RentalController
{
    public function store(SaveRentalOrderCostRequest $request, RentalOrder $pedido, SaveRentalOrderCost $action)
    {
        $this->authorizeWrite($pedido);

        $action->store($pedido, $request->validated());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())], 201);
    }

    public function update(SaveRentalOrderCostRequest $request, RentalOrderCost $custo, SaveRentalOrderCost $action)
    {
        $pedido = $custo->order;
        $this->authorizeWrite($pedido);

        $action->update($custo, $request->validated());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())]);
    }

    public function destroy(RentalOrderCost $custo, SaveRentalOrderCost $action)
    {
        $pedido = $custo->order;
        $this->authorizeWrite($pedido);

        $action->destroy($custo);

        return response()->json(['order' => RentalPresenter::orderDetail($pedido->refresh())]);
    }
}
