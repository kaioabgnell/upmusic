<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Event;
use App\Models\ExternalForm;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExternalFormController extends Controller
{
    public function manage(Board $board)
    {
        $this->authorize('configure', $board);

        $form = $this->resolveForm($board);
        $form->load(['submissions' => fn ($q) => $q->latest()->limit(20), 'targetColumn', 'event']);

        return view('external.manage', [
            'board' => $board->load('columns'),
            'form' => $form,
            'events' => Event::active()->orderByDesc('start_date')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Board $board)
    {
        $this->authorize('configure', $board);

        $form = $this->resolveForm($board);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'target_column_id' => ['nullable', Rule::exists('board_columns', 'id')->where('board_id', $board->id)],
            'event_id' => ['nullable', 'exists:events,id'],
            'active' => ['boolean'],
        ]);
        $data['active'] = $request->boolean('active');

        $form->update($data);

        return back()->with('success', 'Formulário atualizado.');
    }

    public function regenerate(Board $board)
    {
        $this->authorize('configure', $board);

        $this->resolveForm($board)->update(['token' => $this->newToken()]);

        return back()->with('success', 'Novo link gerado. O link anterior deixou de funcionar.');
    }

    /** Garante que exista um formulário para o quadro. */
    private function resolveForm(Board $board): ExternalForm
    {
        return ExternalForm::firstOrCreate(
            ['board_id' => $board->id],
            [
                'token' => $this->newToken(),
                'target_column_id' => $board->columns()->where('is_entry', true)->orderBy('position')->value('id')
                    ?? $board->columns()->orderBy('position')->value('id'),
                'title' => "Envio de dados — {$board->name}",
                'active' => true,
            ],
        );
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(40);
        } while (ExternalForm::where('token', $token)->exists());

        return $token;
    }
}
