<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use Illuminate\Http\Request;

class PriceCategoriaController extends Controller
{
    /**
     * "Banco de preços": lista as categorias de fornecedor com contagem de registros.
     * Leitura liberada a qualquer autenticado (ver specs/15).
     */
    public function index(Request $request)
    {
        // Coordenador restrito por evento (specs/20): a contagem de "Registros" só considera preços
        // dos eventos vinculados a ele (registros sem evento ficam de fora, como nos cards). Admin e
        // coordenador sem restrição contam tudo — allowedEventIds() = null.
        $allowedEventIds = $request->user()->allowedEventIds();

        $categorias = FornecedorCategoria::query()
            ->withCount(['priceRecords' => fn ($q) => $q->when(
                $allowedEventIds !== null,
                fn ($q) => $q->whereNotNull('event_id')->whereIn('event_id', $allowedEventIds),
            )])
            ->when($request->search, fn ($q, $s) => $q->where('nome', 'like', "%{$s}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('precos.categorias.index', compact('categorias'));
    }

    /**
     * Registros de preço de uma categoria (edição inline dos registros).
     */
    public function show(FornecedorCategoria $fornecedorCategoria)
    {
        $fornecedorCategoria->load([
            'priceRecords' => fn ($q) => $q->orderByDesc('reference_date')->orderByDesc('id'),
            'priceRecords.fornecedor:id,name',
            'priceRecords.event:id,name',
        ]);

        return view('precos.categorias.show', [
            'categoria' => $fornecedorCategoria,
            'fornecedores' => Fornecedor::active()->orderBy('name')->get(['id', 'name']),
            'events' => Event::active()->orderByDesc('start_date')->get(['id', 'name']),
        ]);
    }
}
