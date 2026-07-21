<?php

namespace App\Http\Controllers;

use App\Domain\Enums\CardPriority;
use App\Http\Requests\StoreBoardRequest;
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
            ->withCount(['columns', 'cards' => fn ($q) => $q->whereNull('concluded_at')])
            ->when(
                ! $user->isAdmin() && ! $user->isCoordenador(),
                fn ($q) => $q->whereHas('users', fn ($q) => $q->whereKey($user->id))
            )
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

        return view('boards.show', [
            'board' => $board,
            'columns' => $columns,
            'fields' => $fields,
            'filters' => $filters,
            ...$options->globalOptions(),
            'boards' => Board::active()->orderBy('name')->get(['id', 'name']),
            'transferBoards' => Board::active()->where('id', '!=', $board->id)->orderBy('name')->get(['id', 'name']),
            'priorities' => CardPriority::options(),
            'boardTemplates' => CardTemplate::query()->where('active', true)->where('board_id', $board->id)
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

        $filters = $this->filtersFromRequest($request);

        $board->load([
            'columns' => fn ($q) => $q->orderBy('position'),
            'columns.cards' => function ($q) use ($filters) {
                $q->with(['empresa:id,corporate_name,trade_name', 'event:id,name', 'assignee:id,name,avatar_path'])
                    ->withCount(['attachments', 'comments'])
                    ->whereNull('concluded_at')
                    ->whereNull('archived_at')
                    ->when($filters['empresa_id'], fn ($q, $v) => $q->where('empresa_id', $v))
                    ->when($filters['event_id'], fn ($q, $v) => $q->where('event_id', $v))
                    ->when($filters['assignee_id'], fn ($q, $v) => $q->where('assignee_id', $v))
                    ->when($filters['priority'], fn ($q, $v) => $q->where('priority', $v))
                    ->when($filters['search'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
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

        $board->delete();

        return redirect()->route('boards.index')->with('success', 'Quadro excluído com sucesso.');
    }

    public function config(Board $board)
    {
        $this->authorize('configure', $board);

        $board->load(['columns.approvers:id,name', 'fields', 'users:id']);

        return view('boards.config', [
            'board' => $board,
            'users' => User::where('active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'accessIds' => $board->users->pluck('id')->all(),
            'admins' => User::where('active', true)->where('role', 'admin')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateAccess(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $data = $request->validate([
            'users' => ['array'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        $board->users()->sync($data['users'] ?? []);

        return back()->with('success', 'Acesso atualizado com sucesso.');
    }
}
