<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\FinancialEntry;
use App\Models\FinancialPlan;
use App\Support\Br;
use Illuminate\Http\Request;

class FinancialEntryController extends Controller
{
    public function store(Request $request, FinancialPlan $plan)
    {
        $data = $this->validated($request);

        // Ao vincular um card, sugere os valores do card quando ainda vazios.
        if (! empty($data['card_id'])) {
            $card = Card::find($data['card_id']);
            if ($card) {
                $data['estimated_value'] = $data['estimated_value'] ?? $card->estimated_value;
                $data['actual_value'] = $data['actual_value'] ?? $card->actual_value;
            }
        }

        $entry = $plan->entries()->create($data + ['empresa_id' => $plan->empresa_id]);

        return response()->json($this->toArray($entry), 201);
    }

    public function update(Request $request, FinancialEntry $entry)
    {
        $entry->update($this->validated($request));

        return response()->json($this->toArray($entry->fresh()));
    }

    public function destroy(FinancialEntry $entry)
    {
        $entry->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:80'],
            'estimated_value' => ['nullable', 'string'],
            'actual_value' => ['nullable', 'string'],
            'estimated_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
            'card_id' => ['nullable', 'exists:cards,id'],
        ]);

        $data['estimated_value'] = Br::money($data['estimated_value'] ?? null) ?? 0;
        $data['actual_value'] = Br::money($data['actual_value'] ?? null) ?? 0;

        return $data;
    }

    private function toArray(FinancialEntry $e): array
    {
        return [
            'id' => $e->id,
            'description' => $e->description,
            'category' => $e->category,
            'estimated_value' => (float) $e->estimated_value,
            'actual_value' => (float) $e->actual_value,
            'estimated_date' => $e->estimated_date?->format('Y-m-d'),
            'actual_date' => $e->actual_date?->format('Y-m-d'),
            'card_id' => $e->card_id,
        ];
    }
}
