<?php

namespace App\Http\Controllers;

use App\Models\FornecedorCategoria;
use App\Models\PriceRecord;
use App\Support\Br;
use Illuminate\Http\Request;

class PriceRecordController extends Controller
{
    public function store(Request $request, FornecedorCategoria $fornecedorCategoria)
    {
        $this->authorize('update', $fornecedorCategoria);

        $data = $this->validated($request);

        $price = $fornecedorCategoria->priceRecords()->create($data + ['created_by' => $request->user()->id]);
        $price->load(['fornecedor:id,name', 'event:id,name']);

        return response()->json($this->toArray($price), 201);
    }

    public function update(Request $request, PriceRecord $priceRecord)
    {
        $this->authorize('update', $priceRecord->categoria);

        $priceRecord->update($this->validated($request));
        $priceRecord->load(['fornecedor:id,name', 'event:id,name']);

        return response()->json($this->toArray($priceRecord));
    }

    public function destroy(PriceRecord $priceRecord)
    {
        $this->authorize('update', $priceRecord->categoria);

        $priceRecord->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'price' => ['required', 'string'],
            'reference_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['price'] = Br::money($data['price']) ?? 0;

        return $data;
    }

    private function toArray(PriceRecord $p): array
    {
        return [
            'id' => $p->id,
            'price' => (float) $p->price,
            'reference_date' => $p->reference_date->format('Y-m-d'),
            'reference_date_br' => $p->reference_date->format('d/m/Y'),
            'fornecedor_id' => $p->fornecedor_id,
            'fornecedor' => $p->fornecedor?->name,
            'event_id' => $p->event_id,
            'event' => $p->event?->name,
            'notes' => $p->notes,
        ];
    }
}
