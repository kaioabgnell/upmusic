<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RegisterPayment;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Models\FinanceCostItem;
use App\Models\FinancePayment;
use App\Support\FinancePresenter;

/** Pagamentos de uma linha de custo — o "PAGO POR / PAGO / FALTA PAGAR" da planilha. */
class FinancePaymentController extends FinanceController
{
    public function index(FinanceCostItem $item)
    {
        $this->authorize('view', $item->loadMissing('sheet')->sheet);

        return response()->json([
            'payments' => $item->payments()->with(['source:id,name', 'creator:id,name'])
                ->orderByDesc('paid_at')->orderByDesc('id')->get()
                ->map(fn ($p) => FinancePresenter::payment($p))->values(),
            'item' => FinancePresenter::costItem($item),
        ]);
    }

    public function store(StorePaymentRequest $request, FinanceCostItem $item, RegisterPayment $action)
    {
        $this->authorizeWrite($item->loadMissing('sheet')->sheet);

        $payment = $action->execute($item, $request->validated(), $request->user());

        return response()->json([
            'payment' => FinancePresenter::payment($payment),
            'item' => FinancePresenter::costItem($item->refresh()),
        ], 201);
    }

    public function update(StorePaymentRequest $request, FinancePayment $payment)
    {
        $payment->loadMissing('costItem.sheet');
        $this->authorizeWrite($payment->costItem->sheet);

        $payment->update($request->validated());

        return response()->json([
            'payment' => FinancePresenter::payment($payment->refresh()),
            'item' => FinancePresenter::costItem($payment->costItem->refresh()),
        ]);
    }

    public function destroy(FinancePayment $payment)
    {
        $item = $payment->loadMissing('costItem.sheet')->costItem;
        $this->authorizeWrite($item->sheet);

        $payment->delete();

        return response()->json(['ok' => true, 'item' => FinancePresenter::costItem($item->refresh())]);
    }
}
