<?php

namespace App\Http\Controllers\Rentals;

use App\Actions\Rentals\DeleteRentalItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rentals\DestroyRentalItemRequest;
use App\Http\Requests\Rentals\StoreRentalItemRequest;
use App\Http\Requests\Rentals\UpdateRentalItemRequest;
use App\Models\RentalItem;
use App\Models\RentalOrderItem;
use App\Services\Rentals\RentalAvailabilityService;
use Illuminate\Http\Request;

/** Inventário da Gestão de Locações (specs/24 §4.1 / §8.5). */
class RentalItemController extends Controller
{
    public function index(Request $request, RentalAvailabilityService $availability)
    {
        $this->authorize('viewAny', RentalItem::class);

        $withCost = $request->user()->canManageRentals();
        $status = $request->status ?: 'active';
        if ($status === 'deleted' && ! $withCost) {
            $status = 'active';
        }

        $query = RentalItem::query()
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($status === 'deleted' && $withCost, fn ($q) => $q->onlyTrashed())
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('sku', 'like', "%{$s}%")))
            ->orderBy('name');

        $items = $query->paginate(20)->withQueryString();

        return view('locacoes.itens.index', [
            'items' => $items,
            'available' => $availability->availableToday($items->pluck('id')->all()),
            'withCost' => $withCost,
            'status' => $status,
            'totalInvestment' => $withCost ? (clone $query)->sum('total_investment') : null,
        ]);
    }

    public function create()
    {
        $this->authorize('create', RentalItem::class);

        return view('locacoes.itens.form', ['item' => new RentalItem]);
    }

    public function store(StoreRentalItemRequest $request)
    {
        $this->authorize('create', RentalItem::class);

        RentalItem::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('rentals.items.index')->with('success', 'Item cadastrado com sucesso.');
    }

    public function show(Request $request, RentalItem $item, RentalAvailabilityService $availability)
    {
        $this->authorize('view', $item);

        $withCost = $request->user()->canManageRentals();

        $upcoming = RentalOrderItem::where('rental_item_id', $item->id)
            ->whereHas('order', fn ($q) => $q
                ->whereIn('status', ['confirmada', 'em_uso'])
                ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now())))
            ->with('order:id,code,status,start_at,end_at')
            ->get();

        return view('locacoes.itens.show', [
            'item' => $item,
            'withCost' => $withCost,
            'available' => $availability->availableToday([$item->id])[$item->id] ?? $item->quantity,
            'upcoming' => $upcoming,
        ]);
    }

    public function edit(RentalItem $item)
    {
        $this->authorize('update', $item);

        return view('locacoes.itens.form', compact('item'));
    }

    public function update(UpdateRentalItemRequest $request, RentalItem $item)
    {
        $this->authorize('update', $item);

        $item->update($request->validated());

        return redirect()->route('rentals.items.index')->with('success', 'Item atualizado com sucesso.');
    }

    /** Exclusão lógica com motivo obrigatório (specs/24 §2 Q11-Q22) — chamada via SweetAlert2/fetch. */
    public function destroy(DestroyRentalItemRequest $request, RentalItem $item, DeleteRentalItem $action)
    {
        $this->authorize('delete', $item);

        $action->execute($item, $request->validated(), $request->user());

        return response()->json(['ok' => true]);
    }

    /** Autocomplete para o seletor de itens do pedido (specs/24 §8.3) — só itens ativos. */
    public function search(Request $request)
    {
        $this->authorize('viewAny', RentalItem::class);

        $items = RentalItem::active()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'rental_price', 'quantity']);

        return response()->json(['items' => $items]);
    }
}
