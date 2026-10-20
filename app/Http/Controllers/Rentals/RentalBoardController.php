<?php

namespace App\Http\Controllers\Rentals;

use App\Domain\Enums\RentalOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\FinancePaymentSource;
use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Support\RentalPresenter;
use Illuminate\Http\Request;

/** Painel Kanban de Pedidos de Locação (specs/24 §8.2) — etapas fixas, sem coluna configurável. */
class RentalBoardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', RentalOrder::class);

        $showCancelled = $request->boolean('show_cancelled');

        $query = RentalOrder::query()
            ->with('empresa:id,corporate_name,trade_name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$s}%")
                ->orWhere('client_name', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")))
            ->when($request->empresa_id, fn ($q, $id) => $q->where('empresa_id', $id))
            ->when(! $showCancelled, fn ($q) => $q->where('status', '!=', RentalOrderStatus::Cancelada->value));

        $orders = $query->orderBy('position')->orderBy('id')->get();

        $columns = collect(RentalOrderStatus::flow())
            ->push($showCancelled ? RentalOrderStatus::Cancelada : null)
            ->filter()
            ->map(function (RentalOrderStatus $status) use ($orders) {
                $inColumn = $orders->where('status', $status);

                return [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'icon' => $status->icon(),
                    'color' => $status->color(),
                    'cards' => $inColumn->map(fn ($o) => RentalPresenter::orderCard($o))->values(),
                    'totals' => [
                        'count' => $inColumn->count(),
                        'revenue_estimated' => (float) $inColumn->sum('revenue_estimated'),
                    ],
                ];
            })->values();

        return view('locacoes.board', [
            'columns' => $columns,
            'showCancelled' => $showCancelled,
            'search' => $request->search,
            'empresaId' => $request->empresa_id,
            'empresas' => Empresa::active()->orderBy('corporate_name')->get(['id', 'corporate_name', 'trade_name']),
            'items' => RentalItem::active()->orderBy('name')->get(['id', 'name', 'rental_price', 'quantity']),
            'sources' => FinancePaymentSource::active()->ordered()->get(['id', 'name']),
            'canManage' => $request->user()->canManageRentals(),
        ]);
    }
}
