<?php

namespace App\Http\Controllers;

use App\Actions\Templates\ImportTemplate;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CardTemplate::class);

        $templates = CardTemplate::query()
            ->with('board:id,name')
            ->withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('templates.index', [
            'templates' => $templates,
            'boards' => Board::active()->orderBy('name')->get(['id', 'name']),
            'empresas' => Empresa::active()->orderBy('corporate_name')->get(['id', 'corporate_name']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', CardTemplate::class);

        return view('templates.create', ['boards' => Board::active()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(StoreTemplateRequest $request)
    {
        $template = CardTemplate::create($request->validated());

        return redirect()->route('templates.edit', $template)
            ->with('success', 'Template criado. Adicione os cards.');
    }

    public function edit(CardTemplate $template)
    {
        $this->authorize('update', $template);

        $template->load(['items' => fn ($q) => $q->orderBy('position'), 'board.columns', 'board.fields']);

        return view('templates.edit', [
            'template' => $template,
            'boards' => Board::active()->orderBy('name')->get(['id', 'name']),
            'assignees' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateTemplateRequest $request, CardTemplate $template)
    {
        $template->update($request->validated());

        return redirect()->route('templates.edit', $template)->with('success', 'Template atualizado.');
    }

    public function destroy(CardTemplate $template)
    {
        $this->authorize('delete', $template);

        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template excluído.');
    }

    public function import(Request $request, CardTemplate $template, ImportTemplate $action)
    {
        $data = $request->validate([
            'board_id' => ['required', 'exists:boards,id'],
            'empresa_id' => ['nullable', 'exists:empresas,id'],
        ]);

        $board = Board::findOrFail($data['board_id']);
        $this->authorize('create', [Card::class, $board]);

        $empresa = ! empty($data['empresa_id']) ? Empresa::find($data['empresa_id']) : null;

        if ($template->items()->count() === 0) {
            return back()->with('error', 'Este template não possui cards para importar.');
        }

        $count = $action->execute($template, $board, $empresa, $request->user());

        return redirect()->route('boards.show', $board)
            ->with('success', "{$count} card(s) criado(s) a partir do template \"{$template->name}\".");
    }
}
