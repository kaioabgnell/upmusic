<?php

namespace App\Http\Controllers\Finance;

use App\Models\FinanceItemPreset;
use App\Models\FornecedorCategoria;
use Illuminate\Http\Request;

/**
 * Catálogo de descrições de custo por categoria (specs/23 §4.8) — os 168 itens do arquivo modelo
 * mais o que a operação for cadastrando.
 */
class FinanceItemPresetController extends FinanceController
{
    /** Autocomplete da coluna DESCRIÇÃO, filtrado pela categoria escolhida na linha. */
    public function index(Request $request)
    {
        $descriptions = FinanceItemPreset::active()
            ->when($request->categoria_id, fn ($q, $v) => $q->where('fornecedor_categoria_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->orderBy('description')
            ->limit(50)
            ->pluck('description');

        return response()->json(['descriptions' => $descriptions]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fornecedor_categoria_id' => ['required', 'exists:fornecedor_categorias,id'],
            'description' => ['required', 'string', 'max:180'],
        ]);

        // Promover uma descrição que já existe não é erro: só reativa o preset.
        $preset = FinanceItemPreset::withoutGlobalScopes()->firstOrNew([
            'fornecedor_categoria_id' => $data['fornecedor_categoria_id'],
            'description' => trim($data['description']),
        ]);
        $preset->active = true;
        $preset->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $preset->id], 201);
        }

        return back()->with('success', 'Item adicionado ao catálogo.');
    }

    public function update(Request $request, FinanceItemPreset $preset)
    {
        $preset->update($request->validate([
            'description' => ['required', 'string', 'max:180'],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => $request->boolean('active')]);

        return back()->with('success', 'Item atualizado.');
    }

    public function destroy(FinanceItemPreset $preset)
    {
        $preset->delete();

        return back()->with('success', 'Item removido do catálogo.');
    }

    /** Usado pela tela de configurações para trocar a categoria em foco. */
    public function categorias()
    {
        return response()->json(
            FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome'])
        );
    }
}
