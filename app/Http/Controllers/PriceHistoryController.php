<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Services\PriceHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    /** Tamanho da paleta categórica usada no gráfico comparativo (ver specs/02). */
    private const MAX_COMPARE = 8;

    public function __construct(private PriceHistoryService $service) {}

    public function index(Request $request)
    {
        $categoriaId = $request->integer('categoria_id') ?: null;
        $fornecedorId = $request->integer('fornecedor_id') ?: null;
        $period = $request->input('period', '1y');

        $history = collect();
        $comparison = collect();
        $categoria = null;
        $fornecedores = collect();
        $compareData = collect();
        $compareIds = [];

        $periodEnd = Carbon::today();
        $periodStart = match ($period) {
            '3m' => $periodEnd->copy()->subMonths(3),
            '6m' => $periodEnd->copy()->subMonths(6),
            'all' => null,
            default => $periodEnd->copy()->subYear(), // '1y' e qualquer valor desconhecido
        };

        if ($categoriaId) {
            $categoria = FornecedorCategoria::findOrFail($categoriaId);

            $fornecedores = Fornecedor::where('fornecedor_categoria_id', $categoria->id)
                ->orderBy('name')
                ->get(['id', 'name']);

            // Default: primeiro fornecedor por ordem alfabética. Se o fornecedor pedido não
            // pertence à categoria (ex.: troca de categoria mantendo o filtro antigo), cai no primeiro.
            if (! $fornecedorId || ! $fornecedores->contains('id', $fornecedorId)) {
                $fornecedorId = $fornecedores->first()?->id;
            }

            $history = $this->service->historyForCategoria($categoria, $fornecedorId);
            $comparison = $this->service->lastPriceByFornecedor($categoria);

            // Só compara fornecedores que realmente pertencem à categoria selecionada, e limita
            // ao tamanho da paleta categórica (identidade de cor por fornecedor precisa ser estável).
            $requestedIds = collect($request->input('compare_ids', []))->map(fn ($v) => (int) $v)->filter();
            $compareIds = $fornecedores->pluck('id')->intersect($requestedIds)->values()->take(self::MAX_COMPARE)->all();

            if (count($compareIds) >= 2) {
                $compareData = $this->service->compareFornecedores($categoria, $compareIds, $periodStart, $periodEnd);
            }
        }

        return view('precos.evolucao', [
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome', 'unidade']),
            'fornecedores' => $fornecedores,
            'selectedCategoria' => $categoria,
            'selectedFornecedorId' => $fornecedorId,
            'history' => $history,
            'comparison' => $comparison,
            'period' => $period,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'compareIds' => $compareIds,
            'compareData' => $compareData,
        ]);
    }
}
