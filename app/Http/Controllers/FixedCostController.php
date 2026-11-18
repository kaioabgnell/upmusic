<?php

namespace App\Http\Controllers;

use App\Actions\FixedCosts\CreateFixedCost;
use App\Actions\FixedCosts\DeleteFixedCostInstallment;
use App\Actions\FixedCosts\GenerateFixedCostInstallments;
use App\Actions\FixedCosts\RegenerateInstallmentCard;
use App\Actions\FixedCosts\RenewFixedCost;
use App\Actions\FixedCosts\ToggleFixedCost;
use App\Actions\FixedCosts\UpdateFixedCost;
use App\Domain\Enums\FixedCostGenerationMode;
use App\Http\Requests\RenewFixedCostRequest;
use App\Http\Requests\StoreFixedCostRequest;
use App\Http\Requests\UpdateFixedCostRequest;
use App\Models\Board;
use App\Models\Empresa;
use App\Models\FixedCost;
use App\Models\FixedCostInstallment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Cadastro de custos fixos recorrentes (specs/27). */
class FixedCostController extends Controller
{
    /** Vencimento da próxima parcela sem card, direto do cronograma (null = todas geradas). */
    private const NEXT_DUE_SQL = '(select min(i.due_date) from fixed_cost_installments i where i.fixed_cost_id = fixed_costs.id and i.generated_at is null)';

    public function index(Request $request, GenerateFixedCostInstallments $generator)
    {
        $this->authorize('viewAny', FixedCost::class);

        $nextDue = self::NEXT_DUE_SQL;

        $fixedCosts = FixedCost::query()
            ->with(['empresa:id,corporate_name,trade_name', 'board:id,name', 'installments:id,fixed_cost_id,number,due_date,generated_at'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->integer('empresa_id'), fn ($q, $id) => $q->where('empresa_id', $id))
            ->when($request->integer('board_id'), fn ($q, $id) => $q->where('board_id', $id))
            ->when(FixedCostGenerationMode::tryFrom((string) $request->generation_mode), fn ($q, $m) => $q->where('generation_mode', $m->value))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            // Próximo vencimento crescente; os que já geraram tudo vão para o fim.
            ->orderByRaw("{$nextDue} is null")
            ->orderByRaw($nextDue)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Quantos cards a reativação criaria na hora — o aviso do SweetAlert antes de reativar (§5.2).
        $reactivationCounts = $fixedCosts->getCollection()
            ->reject(fn (FixedCost $c) => $c->active)
            ->mapWithKeys(fn (FixedCost $c) => [$c->id => $generator->numbersToGenerate($c)->count()]);

        return view('custos-fixos.index', [
            'fixedCosts' => $fixedCosts,
            'reactivationCounts' => $reactivationCounts,
            'empresas' => Empresa::whereIn('id', FixedCost::select('empresa_id'))->orderBy('corporate_name')
                ->get(['id', 'corporate_name', 'trade_name']),
            'boards' => Board::fixedCosts()->orderBy('name')->get(['id', 'name']),
            'modes' => FixedCostGenerationMode::cases(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', FixedCost::class);

        return view('custos-fixos.create', $this->formOptions());
    }

    public function store(StoreFixedCostRequest $request, CreateFixedCost $action)
    {
        [$cost, $created] = $action->execute($request->validated(), $request->user());

        return redirect()->route('fixed-costs.show', $cost)
            ->with('success', 'Custo fixo cadastrado. '.$this->createdMessage($created));
    }

    public function show(FixedCost $fixedCost, GenerateFixedCostInstallments $generator)
    {
        $this->authorize('view', $fixedCost);

        $fixedCost->load([
            'empresa:id,corporate_name,trade_name',
            'board:id,name',
            'column:id,name',
            'installments.card' => fn ($q) => $q->withTrashed()
                ->select(['id', 'board_id', 'board_column_id', 'concluded_at', 'archived_at', 'deleted_at'])
                ->with(['column:id,name,is_final', 'board:id,name']),
        ]);

        // Antes de "começar na parcela": pagas fora do sistema, sem linha no cronograma.
        $before = collect($fixedCost->start_installment > 1 ? range(1, $fixedCost->start_installment - 1) : [])
            ->map(fn (int $n) => ['number' => $n, 'due_date' => null, 'installment' => null, 'card' => null,
                'status' => 'before_start', 'generation_date' => null, 'paid' => true]);

        $rows = $before->concat($fixedCost->installments->map(fn (FixedCostInstallment $i) => [
            'number' => $i->number,
            'due_date' => $i->due_date,
            'installment' => $i,
            'card' => $i->card,
            'status' => match (true) {
                $i->cardDeleted() => 'card_deleted',
                $i->isGenerated() => 'generated',
                ! $fixedCost->active => 'paused',
                default => 'scheduled',
            },
            'generation_date' => $i->isGenerated() ? null : $fixedCost->generationDateFor($i->number),
            'paid' => $i->isPaid(),
        ]))->values();

        $nextNumber = $fixedCost->nextPendingNumber();

        return view('custos-fixos.show', [
            'fixedCost' => $fixedCost,
            'rows' => $rows,
            'nextNumber' => $nextNumber,
            'nextDueDate' => $nextNumber ? $fixedCost->dueDateFor($nextNumber) : null,
            'reactivationCount' => $fixedCost->active ? 0 : $generator->numbersToGenerate($fixedCost)->count(),
        ]);
    }

    public function edit(FixedCost $fixedCost)
    {
        $this->authorize('update', $fixedCost);

        $fixedCost->load('installments');

        return view('custos-fixos.edit', ['fixedCost' => $fixedCost] + $this->formOptions($fixedCost));
    }

    public function update(UpdateFixedCostRequest $request, FixedCost $fixedCost, UpdateFixedCost $action)
    {
        $data = $request->validated();
        $propagate = (bool) ($data['apply_to_generated'] ?? false);
        unset($data['apply_to_generated']);

        $result = $action->execute($fixedCost, $data, $propagate, $request->user());

        $message = 'Custo fixo atualizado.';
        if ($propagate) {
            $message .= " {$result['propagated']} card(s) em aberto atualizado(s).";
        }
        if ($result['created'] > 0) {
            $message .= ' '.$this->createdMessage($result['created']);
        }

        return redirect()->route('fixed-costs.show', $fixedCost)->with('success', $message);
    }

    public function destroy(FixedCost $fixedCost)
    {
        $this->authorize('delete', $fixedCost);

        // Com parcela gerada não sai (§5.3): os cards apontam para ele. Inativar pausa a geração.
        $generated = $fixedCost->installments()->whereNotNull('generated_at')->count();
        if ($generated > 0) {
            return back()->with('error', "Este custo fixo já gerou {$generated} parcela(s). Inative em vez de excluir.");
        }

        $fixedCost->delete();

        return redirect()->route('fixed-costs.index')->with('success', 'Custo fixo excluído.');
    }

    /** "Gerar próxima parcela agora" (§3.3): só a próxima, mesmo fora da janela. */
    public function generateNext(FixedCost $fixedCost, GenerateFixedCostInstallments $action)
    {
        $this->authorize('update', $fixedCost);

        if (! $fixedCost->active) {
            return back()->with('error', 'Reative o custo fixo antes de gerar parcelas.');
        }

        $number = $fixedCost->nextPendingNumber();
        if ($number === null) {
            return back()->with('error', 'Todas as parcelas já foram geradas. Use "Renovar" para estender.');
        }

        $created = $action->execute($fixedCost, request()->user(), onlyNext: true);

        return back()->with(
            $created ? 'success' : 'error',
            $created
                ? "Parcela {$number} de {$fixedCost->installments_total} gerada."
                : 'A parcela já tinha sido gerada. Atualize a página.'
        );
    }

    /**
     * Exclui uma parcela e renumera as seguintes (specs/27 §5.5). Paga não sai; o card da parcela vai
     * junto para a lixeira.
     */
    public function destroyInstallment(FixedCost $fixedCost, FixedCostInstallment $installment, DeleteFixedCostInstallment $action)
    {
        $this->authorize('update', $fixedCost);

        $number = $installment->number;

        try {
            $action->execute($fixedCost, $installment);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Parcela {$number} excluída. Agora são {$fixedCost->fresh()->installments_total} parcelas.");
    }

    /** "Gerar card novamente" para a parcela cujo card foi excluído (specs/27 §3.4). */
    public function regenerateInstallment(FixedCost $fixedCost, FixedCostInstallment $installment, RegenerateInstallmentCard $action)
    {
        $this->authorize('update', $fixedCost);

        try {
            $card = $action->execute($fixedCost, $installment, request()->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Card #{$card->id} gerado para a parcela {$installment->number}.");
    }

    public function renew(RenewFixedCostRequest $request, FixedCost $fixedCost, RenewFixedCost $action)
    {
        $extra = (int) $request->validated('extra');
        $created = $action->execute($fixedCost, $extra, $request->user());

        $message = "Custo fixo renovado: agora são {$fixedCost->fresh()->installments_total} parcelas.";
        if ($created > 0) {
            $message .= ' '.$this->createdMessage($created);
        }

        return back()->with('success', $message);
    }

    public function toggleActive(FixedCost $fixedCost, ToggleFixedCost $action)
    {
        $this->authorize('update', $fixedCost);

        $created = $action->execute($fixedCost, request()->user());

        $message = $fixedCost->active ? 'Custo fixo reativado.' : 'Custo fixo inativado. Nenhuma parcela nova será gerada.';
        if ($created > 0) {
            $message .= ' '.$this->createdMessage($created);
        }

        return back()->with('success', $message);
    }

    // ------------------------------------------------------------------

    private function createdMessage(int $created): string
    {
        return $created > 0 ? "{$created} card(s) gerado(s)." : 'Nenhum card gerado agora.';
    }

    /**
     * Opções do formulário. Empresas ativas e quadros de custos fixos ativos, mais os que o custo em
     * edição já usa (mesmo inativos), para a edição nunca trocar nada em silêncio. As colunas vão
     * junto, por quadro, para o select dependente.
     */
    private function formOptions(?FixedCost $cost = null): array
    {
        $boards = Board::fixedCosts()
            ->where(fn ($q) => $q->where('active', true)->when($cost, fn ($w) => $w->orWhere('id', $cost->board_id)))
            ->with(['columns' => fn ($q) => $q->orderBy('position')->select(['id', 'board_id', 'name', 'is_entry', 'position'])])
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'empresas' => Empresa::query()
                ->where(fn ($q) => $q->where('active', true)->when($cost, fn ($w) => $w->orWhere('id', $cost->empresa_id)))
                ->orderBy('corporate_name')
                ->get(['id', 'corporate_name', 'trade_name', 'active']),
            'boards' => $boards,
            'boardColumns' => $boards->mapWithKeys(fn (Board $b) => [$b->id => $b->columns->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'is_entry' => (bool) $c->is_entry,
            ])->values()]),
            'modes' => FixedCostGenerationMode::cases(),
        ];
    }
}
