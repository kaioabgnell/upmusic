<?php

namespace App\Services;

use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use Carbon\Carbon;
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
            ->tap(fn ($q) => $this->scopeToAllowedEvents($q))
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
            ->tap(fn ($q) => $this->scopeToAllowedEvents($q))
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

    /**
     * Série comparativa de preços entre vários fornecedores da mesma categoria, dentro de um
     * período. Cada item traz os pontos (data + preço) daquele fornecedor, cronológicos.
     */
    public function compareFornecedores(FornecedorCategoria $categoria, array $fornecedorIds, ?Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($fornecedorIds)) {
            return collect();
        }

        return $categoria->priceRecords()
            ->whereIn('fornecedor_id', $fornecedorIds)
            ->tap(fn ($q) => $this->scopeToAllowedEvents($q))
            ->when($startDate, fn ($q, $v) => $q->where('reference_date', '>=', $v->toDateString()))
            ->where('reference_date', '<=', $endDate->toDateString())
            ->with('fornecedor:id,name')
            ->orderBy('reference_date')
            ->get()
            ->groupBy('fornecedor_id')
            ->map(fn ($group) => [
                'fornecedor_id' => $group->first()->fornecedor_id,
                'fornecedor' => $group->first()->fornecedor?->name,
                'points' => $group->map(fn ($r) => [
                    'date' => $r->reference_date->format('Y-m-d'),
                    'date_br' => $r->reference_date->format('d/m/Y'),
                    'price' => (float) $r->price,
                ])->values(),
            ])
            ->values();
    }

    /**
     * Coordenador restrito por evento (specs/20): a evolução de preços só enxerga registros dos
     * eventos vinculados a ele. Registros sem evento também ficam ocultos, como acontece com os
     * cards. Demais perfis (e coordenador sem restrição) veem tudo — allowedEventIds() = null.
     */
    private function scopeToAllowedEvents($query): void
    {
        $ids = auth()->user()?->allowedEventIds();

        if ($ids !== null) {
            $query->whereNotNull('event_id')->whereIn('event_id', $ids);
        }
    }

    /**
     * Últimos N registros de preço de um fornecedor (mais recente primeiro), com a média e a
     * tendência (evolução/redução) entre o primeiro e o último da janela — usado no tooltip de
     * histórico de preços do card, ao selecionar o fornecedor.
     */
    public function lastForFornecedor(Fornecedor $fornecedor, int $limit = 5): array
    {
        $records = $fornecedor->priceRecords()
            ->orderByDesc('reference_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['price', 'reference_date']);

        if ($records->isEmpty()) {
            return ['records' => [], 'average' => null, 'trend' => null];
        }

        $average = round((float) $records->avg('price'), 2);

        // "Evolução" (tendência de alta) ou "redução" (tendência de baixa) comparando o preço mais
        // recente com o mais antigo da janela — não com o segundo mais recente, para refletir a
        // direção geral dos últimos registros, não só o último salto.
        $newest = (float) $records->first()->price;
        $oldest = (float) $records->last()->price;
        $trend = match (true) {
            $records->count() < 2 || $newest == $oldest => 'estavel',
            $newest > $oldest => 'alta',
            default => 'baixa',
        };

        return [
            'records' => $records->map(fn ($r) => [
                'price' => (float) $r->price,
                'reference_date_br' => $r->reference_date->format('d/m/Y'),
            ])->values()->all(),
            'average' => $average,
            'trend' => $trend,
        ];
    }
}
