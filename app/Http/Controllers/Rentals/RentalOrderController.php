<?php

namespace App\Http\Controllers\Rentals;

use App\Actions\Rentals\CancelRentalOrder;
use App\Actions\Rentals\CheckRentalReturn;
use App\Actions\Rentals\CloseRentalOrder;
use App\Actions\Rentals\CreateRentalOrder;
use App\Actions\Rentals\MoveRentalOrder;
use App\Actions\Rentals\ReactivateRentalOrder;
use App\Actions\Rentals\ReopenRentalOrder;
use App\Actions\Rentals\UpdateRentalOrder;
use App\Domain\Enums\RentalOrderStatus;
use App\Http\Requests\Rentals\CancelRentalOrderRequest;
use App\Http\Requests\Rentals\CheckRentalReturnRequest;
use App\Http\Requests\Rentals\CloseRentalOrderRequest;
use App\Http\Requests\Rentals\MoveRentalOrderRequest;
use App\Http\Requests\Rentals\ReopenRentalOrderRequest;
use App\Http\Requests\Rentals\StoreRentalOrderRequest;
use App\Http\Requests\Rentals\UpdateRentalOrderRequest;
use App\Models\Empresa;
use App\Models\RentalCostPayment;
use App\Models\RentalOrder;
use App\Services\Rentals\RentalOrderSummaryService;
use App\Support\RentalPresenter;
use Illuminate\Http\Request;

/** Pedido de Locação — o "card" do Kanban próprio do módulo (specs/24 §4.2 / §8.3 / §8.4). */
class RentalOrderController extends RentalController
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', RentalOrder::class);

        $query = RentalOrder::query()
            ->with(['empresa:id,corporate_name,trade_name', 'receipts:id,rental_order_id,amount'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$s}%")
                ->orWhere('client_name', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', (array) $s))
            ->when($request->empresa_id, fn ($q, $id) => $q->where('empresa_id', $id))
            ->when($request->rental_item_id, fn ($q, $id) => $q->whereHas(
                'items', fn ($q) => $q->where('rental_item_id', $id)
            ))
            ->when($request->filled('start_at') && $request->filled('end_at'), fn ($q) => $q
                ->where('start_at', '<', $request->end_at)
                ->where('end_at', '>', $request->start_at))
            ->when($request->boolean('receivable'), fn ($q) => $q->whereRaw(
                'COALESCE(revenue_actual, revenue_estimated) > ('
                .'SELECT COALESCE(SUM(amount), 0) FROM rental_receipts'
                .' WHERE rental_receipts.rental_order_id = rental_orders.id)'
            ));

        $orders = (clone $query)->orderByDesc('id')->paginate(20)->withQueryString();
        $totals = app(RentalOrderSummaryService::class)->listTotals(clone $query);

        return view('locacoes.pedidos.index', [
            'orders' => $orders,
            'totals' => $totals,
            'statuses' => RentalOrderStatus::options(),
            'empresas' => Empresa::active()->orderBy('corporate_name')->get(['id', 'corporate_name', 'trade_name']),
        ]);
    }

    public function store(StoreRentalOrderRequest $request, CreateRentalOrder $action)
    {
        $this->authorize('create', RentalOrder::class);

        $pedido = $action->execute($request->validated(), $request->user());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)], 201);
    }

    public function show(RentalOrder $pedido)
    {
        $this->authorize('view', $pedido);

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }

    public function update(UpdateRentalOrderRequest $request, RentalOrder $pedido, UpdateRentalOrder $action)
    {
        $this->authorizeWrite($pedido);

        $pedido = $action->execute($pedido, $request->validated());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }

    /** Admin/coordenador, e só em Orçamento ou Cancelada sem recebimentos nem pagamentos (specs/24 §2.1). */
    public function destroy(RentalOrder $pedido)
    {
        $this->authorize('delete', $pedido);

        abort_unless(
            in_array($pedido->status, [RentalOrderStatus::Orcamento, RentalOrderStatus::Cancelada], true),
            422,
            'Só é possível excluir um pedido em Orçamento ou Cancelado.'
        );

        $hasPayments = RentalCostPayment::whereIn('rental_order_cost_id', $pedido->costs()->pluck('id'))->exists();
        abort_if(
            $pedido->receipts()->exists() || $hasPayments,
            422,
            'Este pedido já tem recebimentos ou pagamentos lançados e não pode ser excluído.'
        );

        $pedido->delete();

        return response()->json(['ok' => true]);
    }

    public function move(MoveRentalOrderRequest $request, RentalOrder $pedido, MoveRentalOrder $mover)
    {
        $this->authorize('update', $pedido);

        $data = $request->validated();
        $target = RentalOrderStatus::from($data['status']);

        if ($pedido->status === $target) {
            $pedido->update(['position' => $data['position'] ?? $pedido->position]);
        } else {
            $pedido = $mover->execute($pedido, $target, $request->user());
            $pedido->update(['position' => $data['position'] ?? 0]);
        }

        return response()->json(['order' => RentalPresenter::orderCard($pedido->refresh())]);
    }

    /** Conferência de devolução (specs/24 §6) + transição para Devolvida, se ainda não estiver lá. */
    public function return(CheckRentalReturnRequest $request, RentalOrder $pedido, CheckRentalReturn $action, MoveRentalOrder $mover)
    {
        $this->authorizeWrite($pedido);

        $hasDivergence = $action->execute($pedido, $request->validated()['returns'] ?? [], $request->user());

        $pedido->refresh();
        if ($pedido->status !== RentalOrderStatus::Devolvida) {
            $mover->execute($pedido, RentalOrderStatus::Devolvida, $request->user());
        }

        return response()->json([
            'order' => RentalPresenter::orderDetail($pedido->refresh()),
            'has_divergence' => $hasDivergence,
        ]);
    }

    public function close(CloseRentalOrderRequest $request, RentalOrder $pedido, CloseRentalOrder $action)
    {
        $this->authorize('close', $pedido);

        $pedido = $action->execute($pedido, $request->validated(), $request->user());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }

    public function reopen(ReopenRentalOrderRequest $request, RentalOrder $pedido, ReopenRentalOrder $action)
    {
        $this->authorize('reopen', $pedido);

        $pedido = $action->execute($pedido, $request->user(), $request->validated()['reason'] ?? null);

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }

    public function cancel(CancelRentalOrderRequest $request, RentalOrder $pedido, CancelRentalOrder $action)
    {
        $this->authorize('cancel', $pedido);

        $pedido = $action->execute($pedido, $request->validated()['reason'], $request->user());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }

    public function reactivate(Request $request, RentalOrder $pedido, ReactivateRentalOrder $action)
    {
        $this->authorize('reactivate', $pedido);

        $pedido = $action->execute($pedido, $request->user());

        return response()->json(['order' => RentalPresenter::orderDetail($pedido)]);
    }
}
