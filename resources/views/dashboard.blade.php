<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Painel</h2>
    </x-slot>

    <x-page-header
        title="Bem-vindo ao UpMusic"
        subtitle="Gestão de processos internos — orçamentos, contratos, financeiro e conclusão."
        icon="fa-gauge-high">
        <x-slot name="actions">
            <a href="{{ route('boards.index') }}" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-table-columns"></i> Ver quadros
            </a>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="bg-white border border-hairline rounded-xl p-5 flex items-center gap-4">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg bg-brand-orange/15 text-brand-orange-deep">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </span>
                <div>
                    <p class="text-sm text-steel">{{ $stat['label'] }}</p>
                    <p class="text-xl font-semibold text-brand-ink">{{ $stat['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Meus quadros --}}
        <div class="lg:col-span-2">
            <h3 class="font-semibold text-brand-ink mb-3">Meus quadros</h3>

            @if ($boards->isEmpty())
                <div class="bg-white border border-hairline rounded-xl">
                    <x-empty-state icon="fa-table-columns" title="Nenhum quadro disponível"
                                   message="Você ainda não tem acesso a nenhum quadro. Contate um administrador." />
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($boards as $board)
                        <a href="{{ route('boards.show', $board) }}" class="bg-white border border-hairline rounded-xl overflow-hidden flex flex-col hover:border-brand-orange transition-colors">
                            <div class="h-1.5" style="background: {{ $board->color }}"></div>
                            <div class="p-4 flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-white shrink-0" style="background: {{ $board->color }}">
                                    <i class="fa-solid {{ $board->icon ?? 'fa-table-columns' }}"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-brand-ink truncate">{{ $board->name }}</p>
                                    <p class="text-xs text-steel">{{ $board->setor?->nome ?? 'Sem setor' }} · {{ $board->cards_count }} cards</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Cards recentes --}}
        <div>
            <h3 class="font-semibold text-brand-ink mb-3">Atualizados recentemente</h3>
            <div class="bg-white border border-hairline rounded-xl divide-y divide-hairline">
                @forelse ($recentCards as $card)
                    <a href="{{ route('boards.show', $card->board_id) }}" class="block p-4 hover:bg-surface/60">
                        <p class="text-sm font-medium text-brand-ink truncate">{{ $card->title }}</p>
                        <p class="text-xs text-steel mt-0.5">{{ $card->board?->name }} @if($card->empresa) · {{ $card->empresa->corporate_name }} @endif</p>
                        <p class="text-xs text-steel mt-0.5">
                            <i class="fa-regular fa-clock mr-1"></i>{{ $card->updated_at->diffForHumans() }}
                            @if ($card->assignee) · {{ $card->assignee->name }} @endif
                        </p>
                    </a>
                @empty
                    <div class="p-4">
                        <x-empty-state icon="fa-clipboard-list" title="Nenhum card ainda" message="Os cards atualizados aparecerão aqui." />
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
