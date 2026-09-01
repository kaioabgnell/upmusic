<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Enums\FinancePaymentSourceKind;
use App\Models\FinanceItemPreset;
use App\Models\FinancePaymentSource;
use App\Models\FornecedorCategoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Configurações do Financeiro (specs/23 §9): grupos de pagamento e catálogo de descrições.
 * Exclusivo do Admin — o middleware `role:admin` cuida disso nas rotas.
 */
class FinancePaymentSourceController extends FinanceController
{
    public function index(Request $request)
    {
        $categoriaId = $request->integer('categoria_id')
            ?: FornecedorCategoria::active()->orderBy('nome')->value('id');

        return view('financeiro.configuracoes', [
            'sources' => FinancePaymentSource::ordered()->with('user:id,name')->get(),
            'kinds' => FinancePaymentSourceKind::options(),
            'usuarios' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->withCount('itemPresets')->get(),
            'categoriaId' => $categoriaId,
            'presets' => FinanceItemPreset::where('fornecedor_categoria_id', $categoriaId)
                ->orderBy('description')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        FinancePaymentSource::create($data + [
            'position' => (int) FinancePaymentSource::max('position') + 1,
        ]);

        return back()->with('success', 'Grupo de pagamento criado.');
    }

    public function update(Request $request, FinancePaymentSource $source)
    {
        $source->update($this->validated($request));

        return back()->with('success', 'Grupo de pagamento atualizado.');
    }

    /**
     * Grupo com pagamento lançado não é excluído — apagar levaria junto o histórico de quem pagou
     * o quê. Nesse caso o correto é desativar, e a UI já oferece o toggle.
     */
    public function destroy(FinancePaymentSource $source)
    {
        if ($source->payments()->exists()) {
            return back()->with('error', 'Este grupo já tem pagamentos lançados. Desative-o em vez de excluir.');
        }

        $source->delete();

        return back()->with('success', 'Grupo de pagamento excluído.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(array_column(FinancePaymentSourceKind::cases(), 'value'))],
            'user_id' => ['nullable', 'exists:users,id'],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => $request->boolean('active')];
    }
}
