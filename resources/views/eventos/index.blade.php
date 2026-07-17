<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Eventos</h2></x-slot>

    <x-page-header title="Eventos" subtitle="Eventos que podem ser vinculados aos orçamentos dos quadros." icon="fa-calendar-days">
        <x-slot name="actions">
            <a href="{{ route('eventos.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo evento
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome do evento" class="pl-9" />
        </div>
        <x-form.select name="status" class="sm:w-40" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($events->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-calendar-days" title="Nenhum evento encontrado" message="Cadastre o primeiro evento." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Evento</th>
                <th class="px-4 py-3 font-medium">Local</th>
                <th class="px-4 py-3 font-medium">Responsável</th>
                <th class="px-4 py-3 font-medium">Período</th>
                <th class="px-4 py-3 font-medium">Cards</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($events as $event)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <p class="font-medium text-brand-ink">{{ $event->name }}</p>
                        @if ($event->email || $event->phone)
                            <p class="text-xs text-steel">{{ $event->phone }}{{ $event->phone && $event->email ? ' · ' : '' }}{{ $event->email }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $event->location ?: '—' }}</td>
                    <td class="px-4 py-3 text-steel">{{ $event->responsible_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-steel">{{ $event->start_date->format('d/m/Y') }} – {{ $event->end_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3"><x-badge variant="neutral">{{ $event->cards_count }}</x-badge></td>
                    <td class="px-4 py-3"><x-badge :variant="$event->active ? 'success' : 'danger'">{{ $event->active ? 'Ativo' : 'Inativo' }}</x-badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('eventos.edit', $event) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('eventos.destroy', $event) }}" data-confirm="Excluir o evento {{ $event->name }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $events->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
