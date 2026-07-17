<?php

namespace App\Http\Controllers;

use App\Actions\Cards\ConcludeCard;
use App\Actions\Cards\CreateCard;
use App\Actions\Cards\MoveCard;
use App\Actions\Cards\ReopenCard;
use App\Actions\Cards\TransferCard;
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
            ->with(['board:id,name', 'column:id,name', 'empresa:id,corporate_name', 'event:id,name', 'assignee:id,name'])
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('empresa_id'), fn ($q) => $q->where('empresa_id', $request->integer('empresa_id')))
            ->when($request->filled('board_id'), fn ($q) => $q->where('board_id', $request->integer('board_id')))
            ->when($request->filled('board_column_id'), fn ($q) => $q->where('board_column_id', $request->integer('board_column_id')))
            ->when($request->input('status') === 'active', fn ($q) => $q->whereNull('concluded_at'))
            ->when($request->input('status') === 'concluded', fn ($q) => $q->whereNotNull('concluded_at'))
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

        $action->execute($card, $column, $data['position'], $request->user());

        return response()->json(['ok' => true]);
    }

    public function transfer(Request $request, Card $card, TransferCard $action)
    {
        $this->authorize('update', $card);

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
            'column:id,name',
            'empresa:id,corporate_name,trade_name',
            'fornecedor:id,name',
            'event:id,name',
            'assignee:id,name',
            'concludedBy:id,name',
            'fieldValues',
            'comments.user:id,name',
            'attachments.uploader:id,name',
            'movements' => fn ($q) => $q->with(['user:id,name', 'fromColumn:id,name', 'toColumn:id,name', 'fromBoard:id,name', 'toBoard:id,name']),
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
                    'reopening' => null,
                    default => $m->fromColumn?->name,
                },
                'to' => match ($m->type->value) {
                    'board' => $m->toBoard?->name,
                    'conclusion' => null,
                    'reopening' => $m->toBoard && $m->toColumn ? "{$m->toBoard->name} / {$m->toColumn->name}" : $m->toColumn?->name,
                    default => $m->toColumn?->name,
                },
                'user' => $m->user?->name,
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
        ];
    }
}
