<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\CardTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TemplateItemController extends Controller
{
    public function store(Request $request, CardTemplate $template)
    {
        $this->authorize('update', $template);

        $data = $this->validateData($request, $template);
        $data['position'] = (int) $template->items()->max('position') + 1;

        $item = $template->items()->create($data);

        return response()->json($this->toArray($item), 201);
    }

    public function update(Request $request, CardTemplateItem $item)
    {
        $this->authorize('update', $item->template);

        $item->update($this->validateData($request, $item->template));

        return response()->json($this->toArray($item->fresh()));
    }

    public function destroy(CardTemplateItem $item)
    {
        $this->authorize('update', $item->template);

        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, CardTemplate $template)
    {
        $this->authorize('update', $template);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:card_template_items,id'],
        ]);

        DB::transaction(function () use ($template, $data) {
            foreach ($data['order'] as $position => $id) {
                $template->items()->whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, CardTemplate $template): array
    {
        $boardId = $template->board_id;

        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'default_column_id' => ['nullable', Rule::exists('board_columns', 'id')->where('board_id', $boardId)],
            'default_fields' => ['nullable', 'array'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['baixa', 'media', 'alta'])],
            'default_assignee_id' => ['nullable', 'exists:users,id'],
        ]);
    }

    private function toArray(CardTemplateItem $i): array
    {
        return [
            'id' => $i->id,
            'title' => $i->title,
            'description' => $i->description,
            'default_column_id' => $i->default_column_id,
            'default_fields' => $i->default_fields ?? (object) [],
            'due_date' => $i->due_date?->format('Y-m-d'),
            'priority' => $i->priority?->value,
            'default_assignee_id' => $i->default_assignee_id,
            'position' => $i->position,
        ];
    }
}
