<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardColumnController extends Controller
{
    public function store(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $data = $this->validateData($request);
        $data['position'] = (int) $board->columns()->max('position') + 1;

        $column = $board->columns()->create($data);

        return response()->json($this->toArray($column), 201);
    }

    public function update(Request $request, BoardColumn $column)
    {
        $this->authorize('configure', $column->board);

        $column->update($this->validateData($request));

        return response()->json($this->toArray($column->fresh()));
    }

    public function destroy(BoardColumn $column)
    {
        $this->authorize('configure', $column->board);

        if ($column->cards()->exists()) {
            return response()->json(['message' => 'Não é possível excluir uma coluna com cards. Mova os cards antes.'], 422);
        }

        $column->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:board_columns,id'],
        ]);

        DB::transaction(function () use ($board, $data) {
            foreach ($data['order'] as $position => $id) {
                $board->columns()->whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'is_final' => ['boolean'],
            'is_entry' => ['boolean'],
        ]);
    }

    private function toArray(BoardColumn $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
            'is_final' => (bool) $c->is_final,
            'is_entry' => (bool) $c->is_entry,
            'position' => $c->position,
        ];
    }
}
