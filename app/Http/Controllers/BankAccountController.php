<?php

namespace App\Http\Controllers;

use App\Domain\Enums\BankAccountType;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\BankAccount;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Cadastro de contas bancárias (specs/26). */
class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccount::query()
            ->with(['empresas' => fn ($q) => $q->select('empresas.id', 'corporate_name', 'trade_name')
                ->orderByRaw('COALESCE(trade_name, corporate_name)')])
            ->withCount(['payments', 'revenues'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('bank', 'like', "%{$s}%")
                ->orWhere('agency', 'like', "%{$s}%")
                ->orWhere('account_number', 'like', "%{$s}%")))
            ->when($request->empresa_id, fn ($q, $id) => $q->whereHas('empresas', fn ($e) => $e->whereKey($id)))
            ->when($request->account_type, fn ($q, $t) => $q->where('account_type', $t))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('contas-bancarias.index', [
            'bankAccounts' => $bankAccounts,
            'empresas' => $this->empresaOptions(),
            'types' => BankAccountType::options(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', BankAccount::class);

        return view('contas-bancarias.create', $this->formData());
    }

    public function store(StoreBankAccountRequest $request)
    {
        DB::transaction(function () use ($request) {
            $account = BankAccount::create(Arr::except($request->validated(), 'empresas'));
            $account->empresas()->sync($request->validated('empresas'));
        });

        return redirect()->route('bank-accounts.index')->with('success', 'Conta bancária criada.');
    }

    public function edit(BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount);

        $bankAccount->load('empresas:id');

        return view('contas-bancarias.edit', $this->formData($bankAccount));
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount)
    {
        DB::transaction(function () use ($request, $bankAccount) {
            $bankAccount->update(Arr::except($request->validated(), 'empresas'));
            $bankAccount->empresas()->sync($request->validated('empresas'));
        });

        return redirect()->route('bank-accounts.index')->with('success', 'Conta bancária atualizada.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $this->authorize('delete', $bankAccount);

        // Em uso não sai: o histórico do financeiro nunca perde de onde saiu/para onde entrou.
        $usage = $bankAccount->usageCount();
        if ($usage > 0) {
            return back()->with('error', "Esta conta está em {$usage} lançamento(s) do financeiro. Inative em vez de excluir.");
        }

        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')->with('success', 'Conta bancária excluída.');
    }

    private function formData(?BankAccount $bankAccount = null): array
    {
        return [
            'bankAccount' => $bankAccount,
            // Ativas + as já vinculadas (mesmo inativas), para a edição não desmarcar nada em silêncio.
            'empresas' => $this->empresaOptions($bankAccount?->empresas->pluck('id')->all() ?? []),
            'types' => BankAccountType::options(),
            'banks' => BankAccount::COMMON_BANKS,
        ];
    }

    /** @param  array<int>  $linkedIds */
    private function empresaOptions(array $linkedIds = [])
    {
        return Empresa::query()
            ->select('id', 'corporate_name', 'trade_name', 'document', 'active')
            ->where(fn ($q) => $q->where('active', true)
                ->when($linkedIds, fn ($w) => $w->orWhereIn('id', $linkedIds)))
            ->orderByRaw('COALESCE(trade_name, corporate_name)')
            ->get();
    }
}
