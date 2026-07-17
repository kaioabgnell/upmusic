<?php

namespace App\Http\Controllers;

use App\Domain\Enums\PessoaTipo;
use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateEmpresaRequest;
use App\Models\Empresa;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use App\Support\Br;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Empresa::class);

        $empresas = Empresa::query()
            ->when($request->search, function ($q, $s) {
                $digits = Br::digits($s);
                $q->where(fn ($q) => $q
                    ->where('corporate_name', 'like', "%{$s}%")
                    ->orWhere('trade_name', 'like', "%{$s}%")
                    ->when($digits !== '', fn ($q) => $q->orWhere('document', 'like', "%{$digits}%")));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderBy('corporate_name')
            ->paginate(15)
            ->withQueryString();

        return view('empresas.index', compact('empresas'));
    }

    public function create()
    {
        $this->authorize('create', Empresa::class);

        return view('empresas.create');
    }

    public function store(StoreEmpresaRequest $request)
    {
        Empresa::create($request->validated());

        return redirect()->route('empresas.index')->with('success', 'Empresa criada com sucesso.');
    }

    public function edit(Empresa $empresa)
    {
        $this->authorize('update', $empresa);

        return view('empresas.edit', compact('empresa'));
    }

    public function update(UpdateEmpresaRequest $request, Empresa $empresa)
    {
        $empresa->update($request->validated());

        return redirect()->route('empresas.index')->with('success', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Empresa $empresa)
    {
        $this->authorize('delete', $empresa);

        $empresa->delete();

        return redirect()->route('empresas.index')->with('success', 'Empresa excluída com sucesso.');
    }

    /**
     * Busca JSON para selects (usada no vínculo de empresa ao card). Qualquer usuário autenticado.
     */
    public function search(Request $request)
    {
        $term = (string) $request->query('q', '');
        $digits = Br::digits($term);

        $empresas = Empresa::query()
            ->active()
            ->where(fn ($q) => $q
                ->where('corporate_name', 'like', "%{$term}%")
                ->orWhere('trade_name', 'like', "%{$term}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('document', 'like', "%{$digits}%")))
            ->orderBy('corporate_name')
            ->limit(20)
            ->get(['id', 'corporate_name', 'trade_name', 'type', 'document']);

        return response()->json(
            $empresas->map(fn ($e) => [
                'id' => $e->id,
                'text' => $e->corporate_name.($e->trade_name ? " ({$e->trade_name})" : ''),
                'document' => $e->type === PessoaTipo::PF ? Br::formatCpf($e->document) : Br::formatCnpj($e->document),
            ])
        );
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
            'corporate_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'type' => ['required', Rule::in(['PF', 'PJ'])],
            'document' => ['required', 'string', $documentRule, Rule::unique('empresas', 'document')->whereNull('deleted_at')],
        ], [
            'document.unique' => 'A empresa informada já está cadastrada no sistema.',
        ], ['corporate_name' => 'razão social', 'document' => $request->input('type') === PessoaTipo::PF->value ? 'CPF' : 'CNPJ']);

        $empresa = Empresa::create($data);

        return response()->json([
            'id' => $empresa->id,
            'text' => $empresa->corporate_name.($empresa->trade_name ? " ({$empresa->trade_name})" : ''),
            'document' => $empresa->type === PessoaTipo::PF ? Br::formatCpf($empresa->document) : Br::formatCnpj($empresa->document),
        ], 201);
    }
}
