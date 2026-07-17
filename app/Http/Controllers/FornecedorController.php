<?php

namespace App\Http\Controllers;

use App\Domain\Enums\PessoaTipo;
use App\Http\Requests\StoreFornecedorRequest;
use App\Http\Requests\UpdateFornecedorRequest;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use App\Services\PriceHistoryService;
use App\Support\Br;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Fornecedor::class);

        $fornecedores = Fornecedor::query()
            ->with('categoria:id,nome')
            ->when($request->search, function ($q, $s) {
                $digits = Br::digits($s);
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$s}%")
                    ->when($digits !== '', fn ($q) => $q->orWhere('document', 'like', "%{$digits}%")));
            })
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('fornecedores.index', compact('fornecedores'));
    }

    public function create()
    {
        $this->authorize('create', Fornecedor::class);

        return view('fornecedores.create', [
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(StoreFornecedorRequest $request)
    {
        Fornecedor::create($request->validated());

        return redirect()->route('fornecedores.index')->with('success', 'Fornecedor criado com sucesso.');
    }

    public function edit(Fornecedor $fornecedor)
    {
        $this->authorize('update', $fornecedor);

        return view('fornecedores.edit', [
            'fornecedor' => $fornecedor,
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function update(UpdateFornecedorRequest $request, Fornecedor $fornecedor)
    {
        $fornecedor->update($request->validated());

        return redirect()->route('fornecedores.index')->with('success', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(Fornecedor $fornecedor)
    {
        $this->authorize('delete', $fornecedor);

        $fornecedor->delete();

        return redirect()->route('fornecedores.index')->with('success', 'Fornecedor excluído com sucesso.');
    }

    /**
     * Cadastro inline (modal) a partir do fluxo do card. Disponível a qualquer autenticado.
     */
    public function quick(Request $request)
    {
        // O documento chega mascarado ("123.456.789-00"); precisa virar dígitos antes da checagem
        // de duplicidade, já que a coluna armazena só dígitos e Rule::unique compara igualdade literal.
        $request->merge(['document' => Br::digits($request->input('document'))]);

        $documentRule = $request->input('type') === PessoaTipo::PF->value ? new Cpf : new Cnpj;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['PF', 'PJ'])],
            'document' => ['required', 'string', $documentRule, Rule::unique('fornecedores', 'document')->whereNull('deleted_at')],
        ], [
            'document.unique' => 'O fornecedor informado já está cadastrado no sistema.',
        ], [
            'name' => 'nome',
            'document' => $request->input('type') === PessoaTipo::PF->value ? 'CPF' : 'CNPJ',
        ]);

        $fornecedor = Fornecedor::create($data);

        return response()->json([
            'id' => $fornecedor->id,
            'name' => $fornecedor->name,
            'document' => $fornecedor->type === PessoaTipo::PF ? Br::formatCpf($fornecedor->document) : Br::formatCnpj($fornecedor->document),
        ], 201);
    }

    /**
     * Últimos 5 preços do fornecedor, para o tooltip de histórico no painel do card.
     */
    public function priceHistory(Fornecedor $fornecedor, PriceHistoryService $service)
    {
        return response()->json($service->lastForFornecedor($fornecedor));
    }
}
