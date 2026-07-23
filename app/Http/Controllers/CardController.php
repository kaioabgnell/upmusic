<?php

namespace App\Http\Controllers;

use App\Actions\Cards\ApproveCard;
use App\Actions\Cards\ArchiveCard;
use App\Actions\Cards\ConcludeCard;
use App\Actions\Cards\CreateCard;
use App\Actions\Cards\DuplicateCard;
use App\Actions\Cards\MoveCard;
use App\Actions\Cards\RejectCard;
use App\Actions\Cards\ReopenCard;
use App\Actions\Cards\TransferCard;
use App\Actions\Cards\UnarchiveCard;
use App\Actions\Cards\UpdateCard;
use App\Domain\Enums\AttachmentKind;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\CardAttachment;
use App\Services\CardFormOptionsService;
use App\Support\CardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CardController extends Controller
{
    /**
     * Listagem global de cards (todos os quadros, todos os status). Ver specs/07/12.
     */
    public function index(Request $request, CardFormOptionsService $options)
    {
        $user = $request->user();
        $isManager = $user->isAdmin() || $user->isCoordenador();

        $boards = Board::query()
            ->when(! $isManager, fn ($q) => $q->whereHas('users', fn ($q) => $q->whereKey($user->id)))
            ->orderBy('name')
            ->get(['id', 'name']);
        $boardIds = $boards->pluck('id');

        $columns = BoardColumn::whereIn('board_id', $boardIds)
            ->when($request->filled('board_id'), fn ($q) => $q->where('board_id', $request->integer('board_id')))
            ->orderBy('board_id')->orderBy('position')
            ->get(['id', 'board_id', 'name']);

        $cards = Card::query()
            ->whereIn('board_id', $boardIds)
            ->visibleTo($user)
            ->with(['board:id,name', 'column:id,name', 'empresa:id,corporate_name', 'event:id,name', 'assignee:id,name'])
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('empresa_id'), fn ($q) => $q->where('empresa_id', $request->integer('empresa_id')))
            ->when($request->filled('board_id'), fn ($q) => $q->where('board_id', $request->integer('board_id')))
            ->when($request->filled('board_column_id'), fn ($q) => $q->where('board_column_id', $request->integer('board_column_id')))
            ->when($request->input('status') === 'active', fn ($q) => $q->whereNull('concluded_at')->whereNull('archived_at'))
            ->when($request->input('status') === 'concluded', fn ($q) => $q->whereNotNull('concluded_at'))
            ->when($request->input('status') === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('cards.index', [
            'cards' => $cards,
            'boards' => $boards,
            'columns' => $columns,
            ...$options->globalOptions(),
            'filters' => $request->only(['search', 'empresa_id', 'board_id', 'board_column_id', 'status']),
        ]);
    }

    public function store(StoreCardRequest $request, Board $board, CreateCard $action)
    {
        $card = $action->execute($board, $request->validated(), $request->user());

        return response()->json(CardPresenter::compact($card), 201);
    }

    public function show(Card $card)
    {
        $this->authorize('view', $card);

        return response()->json($this->cardJson($card));
    }

    public function update(UpdateCardRequest $request, Card $card, UpdateCard $action)
    {
        $card = $action->execute($card, $request->validated(), $request->user());

        return response()->json(CardPresenter::compact($card));
    }

    public function destroy(Card $card)
    {
        $this->authorize('delete', $card);

        $card->delete();

        return response()->json(['ok' => true]);
    }

    public function move(Request $request, Card $card, MoveCard $action)
    {
        $this->authorize('update', $card);

        $data = $request->validate([
            'board_column_id' => ['required', 'exists:board_columns,id'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $column = BoardColumn::findOrFail($data['board_column_id']);
        abort_unless($column->board_id === $card->board_id, 422, 'Coluna inválida para este quadro.');

        // Card numa coluna que exige aprovação só avança por aprovação explícita (ver approve()) —
        // mover pra trás ou entre outras colunas sem avançar continua livre. Ver specs/17 §6.
        abort_if(
            $card->column->requiresApproval() && $column->position > $card->column->position,
            422,
            'Este card precisa ser aprovado antes de avançar.'
        );

        $action->execute($card, $column, $data['position'], $request->user());

        return response()->json(['ok' => true]);
    }

    public function transfer(Request $request, Card $card, TransferCard $action)
    {
        $this->authorize('update', $card);

        // Enviar para outro departamento também é "avançar" — bloqueado enquanto pendente de
        // aprovação, mesma regra do move() acima. Ver specs/17 §6.
        abort_if($card->column->requiresApproval(), 422, 'Este card precisa ser aprovado antes de avançar.');

        $data = $request->validate([
            'board_id' => ['required', 'exists:boards,id'],
            'board_column_id' => ['nullable', 'exists:board_columns,id'],
        ]);

        $toBoard = Board::findOrFail($data['board_id']);
        abort_unless($request->user()->canAccessBoard($toBoard), 403);

        $toColumn = ! empty($data['board_column_id']) ? BoardColumn::find($data['board_column_id']) : null;
        if ($toColumn && $toColumn->board_id !== $toBoard->id) {
            $toColumn = null;
        }

        $action->execute($card, $toBoard, $toColumn, $request->user());

        return response()->json(['ok' => true, 'message' => "Card enviado para {$toBoard->name}."]);
    }

    public function conclude(Card $card, ConcludeCard $action)
    {
        $this->authorize('update', $card);

        abort_if($card->concluded_at, 422, 'Este card já está concluído.');

        $action->execute($card, request()->user());

        return response()->json(['ok' => true, 'message' => 'Card concluído.']);
    }

    public function reopen(Request $request, Card $card, ReopenCard $action)
    {
        $this->authorize('update', $card);

        abort_unless($card->concluded_at, 422, 'Este card não está concluído.');

        $data = $request->validate([
            'board_id' => ['required', 'exists:boards,id'],
            'board_column_id' => ['nullable', 'exists:board_columns,id'],
        ]);

        $toBoard = Board::findOrFail($data['board_id']);
        abort_unless($request->user()->canAccessBoard($toBoard), 403);

        $toColumn = ! empty($data['board_column_id']) ? BoardColumn::find($data['board_column_id']) : null;
        if ($toColumn && $toColumn->board_id !== $toBoard->id) {
            $toColumn = null;
        }

        $action->execute($card, $toBoard, $toColumn, $request->user());

        return response()->json(['ok' => true, 'message' => "Card reaberto em {$toBoard->name}."]);
    }

    public function duplicate(Card $card, DuplicateCard $action)
    {
        $this->authorize('update', $card);

        $duplicate = $action->execute($card, request()->user());

        return response()->json(CardPresenter::compact($duplicate), 201);
    }

    public function archive(Card $card, ArchiveCard $action)
    {
        $this->authorize('update', $card);

        abort_if($card->archived_at, 422, 'Este card já está arquivado.');

        $action->execute($card, request()->user());

        return response()->json(['ok' => true, 'message' => 'Card arquivado.']);
    }

    public function unarchive(Card $card, UnarchiveCard $action)
    {
        $this->authorize('update', $card);

        abort_unless($card->archived_at, 422, 'Este card não está arquivado.');

        $action->execute($card, request()->user());

        return response()->json(['ok' => true, 'message' => 'Card desarquivado.']);
    }

    public function approve(Card $card, ApproveCard $action)
    {
        $this->authorizeApprover($card);

        $approved = $action->execute($card, request()->user());

        // Devolve o card compacto (não só {ok}) para o Kanban reposicionar na nova coluna sem
        // reload — mesmo padrão de duplicate(), que também move/insere um card "novo" na tela.
        return response()->json(CardPresenter::compact($approved) + ['message' => 'Card aprovado.']);
    }

    public function reject(Request $request, Card $card, RejectCard $action)
    {
        $this->authorizeApprover($card);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $action->execute($card, request()->user(), $data['reason']);

        return response()->json(['ok' => true, 'message' => 'Card reprovado e arquivado.']);
    }

    // Checagem manual, DE PROPÓSITO fora de Policy/Gate: Gate::before libera qualquer admin em
    // qualquer ability (ver AuthServiceProvider), o que quebraria a regra de que só o(s)
    // aprovador(es) especificamente configurados para esta coluna podem agir. Ver specs/17 §7.
    private function authorizeApprover(Card $card): void
    {
        abort_unless($card->column->isApproverFor(request()->user()), 403);
    }

    public function storeComment(Request $request, Card $card)
    {
        $this->authorize('view', $card);

        $data = $request->validate(['body' => ['required', 'string']]);

        $comment = $card->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ])->load('user');

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => $comment->user?->name,
            'created_at' => $comment->created_at->format('d/m/Y H:i'),
        ], 201);
    }

    public function storeAttachment(Request $request, Card $card)
    {
        $this->authorize('update', $card);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'kind' => ['nullable', 'in:geral,nota_fiscal,comprovante'],
        ]);

        $file = $request->file('file');
        $path = $file->store("card-attachments/{$card->id}", 'local');

        $attachment = $card->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'kind' => $data['kind'] ?? AttachmentKind::Geral->value,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json($this->attachmentJson($attachment), 201);
    }

    public function destroyAttachment(CardAttachment $attachment)
    {
        $this->authorize('update', $attachment->card);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    public function downloadAttachment(CardAttachment $attachment)
    {
        $this->authorize('view', $attachment->card);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    // ------------------------------------------------------------------

    private function cardJson(Card $card): array
    {
        $card->load([
            'board.fields',
            'column:id,board_id,name,position',
            'column.approvers:id,name',
            'empresa:id,corporate_name,trade_name',
            'fornecedor:id,name',
            'event:id,name',
            'assignee:id,name',
            'concludedBy:id,name',
            'archivedBy:id,name',
            'fieldValues',
            'comments.user:id,name',
            'attachments.uploader:id,name',
            'attachments.supplierSubmission:id,card_attachment_id,note',
            'movements' => fn ($q) => $q->with(['user:id,name', 'fromColumn:id,name', 'toColumn:id,name', 'fromBoard:id,name', 'toBoard:id,name']),
            'supplierForm' => fn ($q) => $q->withCount('submissions'),
        ]);

        return [
            'id' => $card->id,
            'title' => $card->title,
            'description' => $card->description,
            'board_id' => $card->board_id,
            'board_name' => $card->board?->name,
            'board_column_id' => $card->board_column_id,
            'column_name' => $card->column?->name,
            'empresa_id' => $card->empresa_id,
            'empresa' => $card->empresa?->corporate_name,
            'fornecedor_id' => $card->fornecedor_id,
            'fornecedor' => $card->fornecedor?->name,
            'event_id' => $card->event_id,
            'event' => $card->event?->name,
            'assignee_id' => $card->assignee_id,
            'assignee' => $card->assignee?->name,
            'estimated_value' => $card->estimated_value,
            'actual_value' => $card->actual_value,
            'due_date' => $card->due_date?->format('Y-m-d'),
            'priority' => $card->priority->value,
            'origin' => $card->origin->value,
            'concluded_at' => $card->concluded_at?->format('d/m/Y H:i'),
            'concluded_by' => $card->concludedBy?->name,
            'archived_at' => $card->archived_at?->format('d/m/Y H:i'),
            'archived_by' => $card->archivedBy?->name,
            'requires_approval' => (bool) $card->column?->requiresApproval(),
            'approvers' => $card->column?->approvers->pluck('name') ?? [],
            'can_approve' => (bool) $card->column?->isApproverFor(auth()->user()),
            'supplier_form' => $this->supplierFormJson($card),
            'board_fields' => $card->board?->fields->map(fn ($f) => [
                'id' => $f->id,
                'label' => $f->label,
                'type' => $f->type->value,
                'required' => (bool) $f->required,
                'options' => $f->options ?? [],
            ]) ?? [],
            'field_values' => $card->fieldValues->mapWithKeys(fn ($v) => [$v->board_field_id => $v->value]),
            'comments' => $card->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user' => $c->user?->name,
                'created_at' => $c->created_at->format('d/m/Y H:i'),
            ]),
            'attachments' => $card->attachments->map(fn ($a) => $this->attachmentJson($a)),
            'movements' => $card->movements->map(fn ($m) => [
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'from' => match ($m->type->value) {
                    'board' => $m->fromBoard?->name,
                    'reopening', 'unarchival' => null,
                    default => $m->fromColumn?->name,
                },
                'to' => match ($m->type->value) {
                    'board' => $m->toBoard?->name,
                    'conclusion', 'archival', 'rejection' => null,
                    'reopening' => $m->toBoard && $m->toColumn ? "{$m->toBoard->name} / {$m->toColumn->name}" : $m->toColumn?->name,
                    default => $m->toColumn?->name,
                },
                'user' => $m->user?->name,
                'note' => $m->note,
                'created_at' => $m->created_at->format('d/m/Y H:i'),
            ]),
        ];
    }

    private function attachmentJson(CardAttachment $a): array
    {
        return [
            'id' => $a->id,
            'kind' => $a->kind->value,
            'kind_label' => $a->kind->label(),
            'original_name' => $a->original_name,
            'size' => $a->size,
            'url' => route('cards.attachments.download', $a),
            // Observação do fornecedor ao enviar a minuta (specs/19) — null para os demais anexos.
            'note' => $a->supplierSubmission?->note,
        ];
    }

    /**
     * Estado do formulário de minuta do fornecedor para o modal do card (specs/19). `allowed` reflete
     * o toggle do quadro; `url`/`active`/`submissions_count` só vêm quando já existe um link.
     */
    private function supplierFormJson(Card $card): array
    {
        $form = $card->supplierForm;

        return [
            'allowed' => (bool) $card->board?->allows_supplier_form,
            'active' => (bool) $form?->active,
            'url' => $form && $form->active ? route('supplier.form.show', $form->token) : null,
            'submissions_count' => $form?->submissions_count ?? 0,
        ];
    }
}
