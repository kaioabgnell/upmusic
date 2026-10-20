<?php

namespace App\Http\Controllers\Rentals;

use App\Http\Controllers\Controller;
use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Services\Rentals\RentalAvailabilityService;
use Illuminate\Http\Request;

/** Disponibilidade por período (specs/24 §7.3/§9) — só informa, nunca bloqueia. */
class RentalAvailabilityController extends Controller
{
    public function index(Request $request, RentalAvailabilityService $service)
    {
        $this->authorize('viewAny', RentalOrder::class);

        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'order' => ['nullable', 'integer', 'exists:rental_orders,id'],
        ]);

        $itemIds = RentalItem::query()->active()->pluck('id')->all();
        $available = $service->availableQuantities($itemIds, $data['start'], $data['end'], $data['order'] ?? null);

        return response()->json([
            'availability' => collect($available)
                ->map(fn ($qty, $id) => ['rental_item_id' => $id, 'available' => $qty])
                ->values(),
        ]);
    }
}
