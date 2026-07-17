<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->withCount('cards')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->status === 'active'))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('eventos.index', compact('events'));
    }

    public function create()
    {
        $this->authorize('create', Event::class);

        return view('eventos.create');
    }

    public function store(StoreEventRequest $request)
    {
        Event::create($request->validated());

        return redirect()->route('eventos.index')->with('success', 'Evento criado com sucesso.');
    }

    public function edit(Event $evento)
    {
        $this->authorize('update', $evento);

        return view('eventos.edit', ['event' => $evento]);
    }

    public function update(UpdateEventRequest $request, Event $evento)
    {
        $evento->update($request->validated());

        return redirect()->route('eventos.index')->with('success', 'Evento atualizado com sucesso.');
    }

    public function destroy(Event $evento)
    {
        $this->authorize('delete', $evento);

        if ($evento->cards()->exists()) {
            return back()->with('error', 'Não é possível excluir um evento com cards vinculados.');
        }

        $evento->delete();

        return redirect()->route('eventos.index')->with('success', 'Evento excluído com sucesso.');
    }
}
