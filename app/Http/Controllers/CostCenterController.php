<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCostCenterRequest;
use App\Http\Requests\UpdateCostCenterRequest;
use App\Models\CostCenter;
use Illuminate\Http\Request;

/** Cadastro de centros de custo (specs/25). */
class CostCenterController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CostCenter::class);

        $costCenters = CostCenter::query()
            ->withCount(['costItems', 'revenues'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('centros-de-custo.index', compact('costCenters'));
    }

    public function create()
    {
        $this->authorize('create', CostCenter::class);

        return view('centros-de-custo.create');
    }

    public function store(StoreCostCenterRequest $request)
    {
        CostCenter::create($request->validated());

        return redirect()->route('cost-centers.index')->with('success', 'Centro de custo criado.');
    }

    public function edit(CostCenter $costCenter)
    {
        $this->authorize('update', $costCenter);

        return view('centros-de-custo.edit', compact('costCenter'));
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter)
    {
        $costCenter->update($request->validated());

        return redirect()->route('cost-centers.index')->with('success', 'Centro de custo atualizado.');
    }

    public function destroy(CostCenter $costCenter)
    {
        $this->authorize('delete', $costCenter);

        // Em uso não sai: o histórico do financeiro nunca perde a classificação. Inativar resolve.
        $usage = $costCenter->usageCount();
        if ($usage > 0) {
            return back()->with('error', "Este centro está em {$usage} linha(s) do financeiro. Inative em vez de excluir.");
        }

        $costCenter->delete();

        return redirect()->route('cost-centers.index')->with('success', 'Centro de custo excluído.');
    }
}
