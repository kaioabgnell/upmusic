<?php

namespace App\Http\Controllers;

use App\Domain\Enums\CardPriority;
use App\Http\Requests\StoreBoardRequest;
use App\Http\Requests\UpdateBoardFixedCostsRequest;
use App\Http\Requests\UpdateBoardRequest;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Setor;
use App\Models\User;
use App\Services\CardFormOptionsService;
use App\Support\CardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Board::class);

        $user = auth()->user();

        $boards = Board::query()
            ->with('setor')
            // Contagem por quadro respeita o escopo por evento do coordenador restrito (specs/20).
            ->withCount(['columns', 'cards' => fn ($q) => $q->whereNull('concluded_at')->visibleTo($user)])
            // Usuário só vê os vinculados; quadros de custos fixos só para quem gerencia (specs/27 §1.2).
            ->visibleTo($user)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('boards.index', compact('boards'));
    }

    public function show(Request $request, Board $board, CardFormOptionsService $options, ?Card $card = null)
    {
        $this->authorize('view', $board);

        // Link direto do card (specs/18): /quadros/{board}/card/{card} reaproveita esta mesma tela —
        // card de outro quadro na URL (id editado manualmente) vira 404, mesmo padrão de checagem
        // cruzada já usado em CardController::move().
        abort_if($card && $card->board_id !== $board->id, 404);

        $filters = $this->filtersFromRequest($request);

        $board->load([
            'setor',
            'fields',
            'columns' => fn ($q) => $q->orderBy('position'),
        ]);

        $columns = $board->columns->map(fn ($col) => [
            'id' => $col->id,
            'name' => $col->name,
            'color' => $col->color,
            'is_entry' => (bool) $col->is_entry,
            'is_final' => (bool) $col->is_final,
        ])->values();

        $fields = $board->fields->map(fn ($f) => [
            'id' => $f->id,
            'label' => $f->label,
            'type' => $f->type->value,
            'required' => (bool) $f->required,
            'options' => $f->options ?? [],
        ])->values();

        $globalOptions = $options->globalOptions();

        // Quadro de custos fixos (specs/27 §1.2): responsável só entre quem enxerga o quadro.
        if ($board->fixed_costs) {
            $globalOptions['assignees'] = $options->fixedCostAssignees();
        }

        // Reabrir e enviar para outro departamento só entre quadros do mesmo tipo (specs/27 §1.3).
        $sameType = Board::active()->visibleTo($request->user())->where('fixed_costs', $board->fixed_costs)
            ->orderBy('name')->get(['id', 'name', 'fixed_costs']);

        return view('boards.show', [
            'board' => $board,
            'columns' => $columns,
            'fields' => $fields,
            'filters' => $filters,
            ...$globalOptions,
            'boards' => $sameType->values(),
            'transferBoards' => $sameType->where('id', '!=', $board->id)->values(),
            'priorities' => CardPriority::options(),
            // Templates não se aplicam aos quadros de custos fixos (specs/27 §1.3).
            'boardTemplates' => $board->fixed_costs ? collect() : CardTemplate::query()->where('active', true)->where('board_id', $board->id)
                ->withCount('items')->orderBy('name')->get(['id', 'name']),
            'openCardId' => $card?->id,
        ]);
    }

    /**
     * Dados assíncronos do quadro (colunas + cards compactos), buscados pelo front após o shell
     * da página já estar na tela. Ver specs/14.
     */
    public function kanbanData(Request $request, Board $board)
    {
        $this->authorize('view', $board);

        $user = $request->user();
        $filters = $this->filtersFromRequest($request);

        $board->load([
            'columns' => fn ($q) => $q->orderBy('position'),
            'columns.cards' => function ($q) use ($filters, $user) {
                $q->with([
                    'empresa:id,corporate_name,trade_name', 'fornecedor:id,name',
                    'event:id,name', 'assignee:id,name,avatar_path',
                    // Selo "Parcela X de Y" (specs/27 §4) sem N+1.
                    'fixedCostInstallment:id,fixed_cost_id,number', 'fixedCostInstallment.fixedCost:id,installments_total',
                ])
                    ->withCount(['attachments', 'comments'])
                    ->withExists('financeCostItem')
                    ->whereNull('concluded_at')
                    ->whereNull('archived_at')
                    ->visibleTo($user)
                    ->when($filters['empresa_id'], fn ($q, $v) => $q->where('empresa_id', $v))
                    ->when($filters['event_id'], fn ($q, $v) => $q->where('event_id', $v))
                    ->when($filters['assignee_id'], fn ($q, $v) => $q->where('assignee_id', $v))
                    ->when($filters['priority'], fn ($q, $v) => $q->where('priority', $v))
                    ->when($filters['search'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
                    // Filtro "Tipo" dos quadros de custos fixos (specs/27 §4.1).
                    ->when($filters['kind'] === 'installments', fn ($q) => $q->whereNotNull('fixed_cost_installment_id'))
                    ->when($filters['kind'] === 'manual', fn ($q) => $q->whereNull('fixed_cost_installment_id'))
                    ->orderBy('position');
            },
        ]);

        $columns = $board->columns->map(fn ($col) => [
            'id' => $col->id,
            'name' => $col->name,
            'color' => $col->color,
            'is_entry' => (bool) $col->is_entry,
            'is_final' => (bool) $col->is_final,
            'cards' => $col->cards->map(fn ($card) => CardPresenter::compact($card))->values(),
        ])->values();

        return response()->json(['columns' => $columns]);
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'empresa_id' => $request->integer('empresa_id') ?: null,
            'event_id' => $request->integer('event_id') ?: null,
            'assignee_id' => $request->integer('assignee_id') ?: null,
            'priority' => $request->input('priority') ?: null,
            'search' => $request->input('search') ?: null,
            'kind' => in_array($request->input('kind'), ['installments', 'manual'], true) ? $request->input('kind') : null,
        ];
    }

    public function create()
    {
        $this->authorize('create', Board::class);

        return view('boards.create', ['setores' => Setor::active()->orderBy('nome')->get()]);
    }

    public function store(StoreBoardRequest $request)
    {
        $data = $request->validated();
        $withDefaults = $data['with_default_columns'] ?? false;
        unset($data['with_default_columns']);

        $data['position'] = (int) Board::max('position') + 1;

        $board = DB::transaction(function () use ($data, $withDefaults) {
            $board = Board::create($data);

            if ($withDefaults) {
                foreach ([
                    ['name' => 'A Fazer', 'is_entry' => true, 'is_final' => false],
                    ['name' => 'Em Andamento', 'is_entry' => false, 'is_final' => false],
                    ['name' => 'Concluído', 'is_entry' => false, 'is_final' => true],
                ] as $i => $col) {
                    $board->columns()->create($col + ['position' => $i]);
                }
            }

            return $board;
        });

        return redirect()->route('boards.config', $board)
            ->with('success', 'Quadro criado. Configure as colunas e campos.');
    }

    public function edit(Board $board)
    {
        $this->authorize('update', $board);

        return view('boards.edit', [
            'board' => $board,
            'setores' => Setor::active()->orderBy('nome')->get(),
        ]);
    }

    public function update(UpdateBoardRequest $request, Board $board)
    {
        $board->update($request->validated());

        return redirect()->route('boards.index')->with('success', 'Quadro atualizado com sucesso.');
    }

    public function destroy(Board $board)
    {
        $this->authorize('delete', $board);

        $fixedCosts = $board->fixedCostItems()->count();
        if ($fixedCosts > 0) {
            return back()->with('error', "{$fixedCosts} custo(s) fixo(s) geram cards neste quadro. Troque o quadro de destino deles antes de excluir.");
        }

        $board->delete();

        return redirect()->route('boards.index')->with('success', 'Quadro excluído com sucesso.');
    }

    public function config(Board $board)
    {
        $this->authorize('configure', $board);

        $board->load(['columns.approvers:id,name', 'fields', 'accessProfiles:id,name']);

        return view('boards.config', [
            'board' => $board,
            // Aprovador = qualquer usuário ativo com acesso ao quadro (specs/28 §5).
            'approverCandidates' => User::where('active', true)->withBoardAccess($board)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Toggle "Permitir solicitar minuta ao fornecedor" (specs/19). Salvo via JSON pela tela de
     * configuração (mesmo padrão dos aprovadores da spec 17).
     */
    public function updateSupplierForm(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $board->update(['allows_supplier_form' => $request->boolean('allows_supplier_form')]);

        return response()->json(['ok' => true, 'allows_supplier_form' => $board->allows_supplier_form]);
    }

    /**
     * Chave "Quadro de custos fixos" (specs/27 §1.1). Exclusiva com `feeds_finance` e travada enquanto
     * algum custo fixo gera cards aqui — as duas regras moram no Form Request.
     */
    public function updateFixedCosts(UpdateBoardFixedCostsRequest $request, Board $board)
    {
        $board->update(['fixed_costs' => $request->boolean('fixed_costs')]);

        return response()->json(['ok' => true, 'fixed_costs' => $board->fixed_costs]);
    }
}
