<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Services\PriceHistoryService;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    public function __construct(private PriceHistoryService $service) {}

    public function index(Request $request)
    {
        $categoriaId = $request->integer('categoria_id') ?: null;
        $fornecedorId = $request->integer('fornecedor_id') ?: null;

        $history = collect();
        $comparison = collect();
        $categoria = null;
        $fornecedores = collect();

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
        }

        return view('precos.evolucao', [
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome', 'unidade']),
            'fornecedores' => $fornecedores,
            'selectedCategoria' => $categoria,
            'selectedFornecedorId' => $fornecedorId,
            'history' => $history,
            'comparison' => $comparison,
        ]);
    }
}
