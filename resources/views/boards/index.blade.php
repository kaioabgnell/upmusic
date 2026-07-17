<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Quadros</h2></x-slot>

    <x-page-header title="Quadros / Processos" subtitle="Cada quadro representa um departamento e seu fluxo de trabalho." icon="fa-table-columns">
        <x-slot name="actions">
            @can('create', App\Models\Board::class)
                <a href="{{ route('boards.create') }}"
                   class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                    <i class="fa-solid fa-plus"></i> Novo quadro
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    @if ($boards->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-table-columns" title="Nenhum quadro disponível"
                           message="Crie um quadro para começar a organizar seus processos." />
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($boards as $board)
                <div class="bg-white border border-hairline rounded-xl overflow-hidden flex flex-col">
                    <div class="h-1.5" style="background: {{ $board->color }}"></div>
                    <div class="p-5 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-white shrink-0" style="background: {{ $board->color }}">
                                    <i class="fa-solid {{ $board->icon ?? 'fa-table-columns' }}"></i>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-brand-ink truncate">{{ $board->name }}</h3>
                                    <p class="text-xs text-steel">{{ $board->setor?->nome ?? 'Sem setor' }}</p>
                                </div>
                            </div>
                            @unless ($board->active)
                                <x-badge variant="danger">Inativo</x-badge>
                            @endunless
                        </div>

                        @if ($board->description)
                            <p class="text-sm text-steel mt-3 line-clamp-2">{{ $board->description }}</p>
                        @endif

                        <div class="flex items-center gap-4 mt-4 text-xs text-steel">
                            <span><i class="fa-solid fa-list-check mr-1"></i>{{ $board->columns_count }} etapas</span>
                            <span><i class="fa-solid fa-clipboard-list mr-1"></i>{{ $board->cards_count }} cards</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 px-5 py-3 border-t border-hairline">
                        <a href="{{ route('boards.show', $board) }}"
                           class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-3 py-1.5 text-sm font-medium text-white hover:bg-black transition-colors">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Abrir
                        </a>
                        @can('configure', $board)
                            <a href="{{ route('boards.config', $board) }}"
                               class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-1.5 text-sm font-medium text-brand-ink hover:bg-surface">
                                <i class="fa-solid fa-gear"></i> Configurar
                            </a>
                        @endcan
                        @can('update', $board)
                            <a href="{{ route('boards.edit', $board) }}" class="ml-auto inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar dados"><i class="fa-solid fa-pen"></i></a>
                        @endcan
                        @can('delete', $board)
                            <form method="POST" action="{{ route('boards.destroy', $board) }}" data-confirm="Excluir o quadro {{ $board->name }}?" class="{{ auth()->user()->can('update', $board) ? '' : 'ml-auto' }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
