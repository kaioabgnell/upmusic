<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFornecedorCategoriaRequest;
use App\Http\Requests\UpdateFornecedorCategoriaRequest;
use App\Models\FornecedorCategoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FornecedorCategoriaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', FornecedorCategoria::class);

        $categorias = FornecedorCategoria::query()
            ->withCount('fornecedores')
            ->when($request->search, fn ($q, $s) => $q->where('nome', 'like', "%{$s}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('fornecedor-categorias.index', compact('categorias'));
    }

    public function create()
    {
        $this->authorize('create', FornecedorCategoria::class);

        return view('fornecedor-categorias.create');
    }

    public function store(StoreFornecedorCategoriaRequest $request)
    {
        FornecedorCategoria::create($request->validated());

        return redirect()->route('fornecedor-categorias.index')->with('success', 'Categoria criada com sucesso.');
    }

    public function edit(FornecedorCategoria $fornecedorCategoria)
    {
        $this->authorize('update', $fornecedorCategoria);

        return view('fornecedor-categorias.edit', ['categoria' => $fornecedorCategoria]);
    }

    public function update(UpdateFornecedorCategoriaRequest $request, FornecedorCategoria $fornecedorCategoria)
    {
        $fornecedorCategoria->update($request->validated());

        return redirect()->route('fornecedor-categorias.index')->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(FornecedorCategoria $fornecedorCategoria)
    {
        $this->authorize('delete', $fornecedorCategoria);

        if ($fornecedorCategoria->fornecedores()->exists()) {
            return back()->with('error', 'Não é possível excluir uma categoria com fornecedores vinculados.');
        }

        $fornecedorCategoria->delete();

        return redirect()->route('fornecedor-categorias.index')->with('success', 'Categoria excluída com sucesso.');
    }

    /**
     * Cadastro inline (a partir do select no formulário de fornecedor). Admin/Coordenador.
     */
    public function quick(Request $request)
    {
        $this->authorize('create', FornecedorCategoria::class);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('fornecedor_categorias', 'nome')->whereNull('deleted_at')],
        ], [
            'nome.unique' => 'Essa categoria já está cadastrada.',
        ], ['nome' => 'nome']);

        $categoria = FornecedorCategoria::create($data);

        return response()->json(['id' => $categoria->id, 'nome' => $categoria->nome], 201);
    }
}
