<?php

namespace App\Services;

use App\Models\FornecedorCategoria;
use Illuminate\Support\Collection;

/**
 * Evolução histórica de preços por categoria de fornecedor (ver specs/15). Calcula variação
 * absoluta e percentual entre registros consecutivos de uma série ordenada.
 */
class PriceHistoryService
{
    /**
     * Série da categoria (todos os eventos), do mais recente ao mais antigo,
     * com variação em relação ao registro anterior (cronologicamente).
     * Opcionalmente restrita a um fornecedor.
     */
    public function historyForCategoria(FornecedorCategoria $categoria, ?int $fornecedorId = null): Collection
    {
        $prices = $categoria->priceRecords()
            ->with(['fornecedor:id,name', 'card:id,title', 'event:id,name'])
            ->when($fornecedorId, fn ($q, $v) => $q->where('fornecedor_id', $v))
            ->orderBy('reference_date')
            ->orderBy('id')
            ->get();

        $previous = null;
        $withVariation = $prices->map(function ($price) use (&$previous) {
            $variation = null;
            $variationPct = null;
            if ($previous !== null) {
                $variation = (float) $price->price - (float) $previous;
                $variationPct = $previous > 0 ? round($variation / $previous * 100, 1) : null;
            }
            $previous = (float) $price->price;

            return [
                'id' => $price->id,
                'price' => (float) $price->price,
                'reference_date' => $price->reference_date->format('Y-m-d'),
                'reference_date_br' => $price->reference_date->format('d/m/Y'),
                'fornecedor' => $price->fornecedor?->name,
                'event' => $price->event?->name,
                'card' => $price->card?->title,
                'notes' => $price->notes,
                'variation' => $variation,
                'variation_pct' => $variationPct,
            ];
        });

        // Retorna do mais recente para o mais antigo (para exibição em tabela).
        return $withVariation->reverse()->values();
    }

    /** Último preço registrado por fornecedor, para comparação entre fornecedores da categoria. */
    public function lastPriceByFornecedor(FornecedorCategoria $categoria): Collection
    {
        return $categoria->priceRecords()
            ->with('fornecedor:id,name')
            ->whereNotNull('fornecedor_id')
            ->get()
            ->groupBy('fornecedor_id')
            ->map(function ($group) {
                $latest = $group->sortByDesc('reference_date')->first();

                return [
                    'fornecedor' => $latest->fornecedor?->name,
                    'price' => (float) $latest->price,
                    'reference_date' => $latest->reference_date->format('d/m/Y'),
                ];
            })
            ->sortBy('fornecedor')
            ->values();
    }
}
