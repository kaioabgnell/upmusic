<?php

namespace App\Http\Controllers;

use App\Domain\Enums\FieldType;
use App\Models\Board;
use App\Models\BoardField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BoardFieldController extends Controller
{
    public function store(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $data = $this->validateData($request);
        $data['position'] = (int) $board->fields()->max('position') + 1;

        $field = $board->fields()->create($data);

        return response()->json($this->toArray($field), 201);
    }

    public function update(Request $request, BoardField $field)
    {
        $this->authorize('configure', $field->board);

        $field->update($this->validateData($request));

        return response()->json($this->toArray($field->fresh()));
    }

    public function destroy(BoardField $field)
    {
        $this->authorize('configure', $field->board);

        $field->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:board_fields,id'],
        ]);

        DB::transaction(function () use ($board, $data) {
            foreach ($data['order'] as $position => $id) {
                $board->fields()->whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_column(FieldType::cases(), 'value'))],
            'required' => ['boolean'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:120'],
        ]);

        // options só faz sentido para o tipo 'select'
        if (($data['type'] ?? null) !== FieldType::Select->value) {
            $data['options'] = null;
        }

        return $data;
    }

    private function toArray(BoardField $f): array
    {
        return [
            'id' => $f->id,
            'label' => $f->label,
            'type' => $f->type->value,
            'type_label' => $f->type->label(),
            'required' => (bool) $f->required,
            'options' => $f->options ?? [],
            'position' => $f->position,
        ];
    }
}
