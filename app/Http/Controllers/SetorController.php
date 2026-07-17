<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSetorRequest;
use App\Http\Requests\UpdateSetorRequest;
use App\Models\Setor;
use Illuminate\Http\Request;

class SetorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Setor::class);

        $setores = Setor::query()
            ->withCount('boards')
            ->when($request->search, fn ($q, $s) => $q->where('nome', 'like', "%{$s}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('setores.index', compact('setores'));
    }

    public function create()
    {
        $this->authorize('create', Setor::class);

        return view('setores.create');
    }

    public function store(StoreSetorRequest $request)
    {
        Setor::create($request->validated());

        return redirect()->route('setores.index')->with('success', 'Setor criado com sucesso.');
    }

    public function edit(Setor $setor)
    {
        $this->authorize('update', $setor);

        return view('setores.edit', compact('setor'));
    }

    public function update(UpdateSetorRequest $request, Setor $setor)
    {
        $setor->update($request->validated());

        return redirect()->route('setores.index')->with('success', 'Setor atualizado com sucesso.');
    }

    public function destroy(Setor $setor)
    {
        $this->authorize('delete', $setor);

        if ($setor->boards()->exists()) {
            return back()->with('error', 'Não é possível excluir um setor com quadros vinculados.');
        }

        $setor->delete();

        return redirect()->route('setores.index')->with('success', 'Setor excluído com sucesso.');
    }
}
