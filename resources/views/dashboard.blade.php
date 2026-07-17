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

    {{-- Busca centralizada de cards — mesmo parâmetro/rota do filtro em "Todos os cards". --}}
    <form method="GET" action="{{ route('cards.index') }}" class="mb-6">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-steel"></i>
            <input type="text" name="search" placeholder="Pesquisar cards por título..."
                   class="w-full h-12 pl-11 pr-24 rounded-xl border-hairline focus:border-brand-orange focus:ring-brand-orange text-sm shadow-sm">
            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 h-8 inline-flex items-center gap-1.5 rounded-md bg-brand-ink px-3 text-xs font-semibold text-white hover:bg-black transition-colors">
                Pesquisar
            </button>
        </div>
    </form>

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

    {{-- 3 colunas lado a lado: vencendo hoje / meus quadros / atualizados recentemente. --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna 1: vencendo hoje — o dado mais urgente, com destaque visual (borda vermelha + selo). --}}
        <div class="bg-white border border-hairline rounded-xl overflow-hidden flex flex-col">
            <div class="h-1 bg-red-500 shrink-0"></div>
            <div class="p-4 border-b border-hairline shrink-0">
                <h3 class="font-semibold text-brand-ink flex items-center gap-2">
                    <i class="fa-solid fa-calendar-day text-red-500"></i> Vencendo hoje
                    @if ($dueTodayCards->isNotEmpty())
                        <x-badge variant="danger">{{ $dueTodayCards->count() }}</x-badge>
                    @endif
                </h3>
            </div>

            @if ($dueTodayCards->isEmpty())
                <x-empty-state icon="fa-circle-check" title="Nenhum card vence hoje" message="Os cards com vencimento para hoje aparecerão aqui." />
            @else
                <div class="divide-y divide-hairline max-h-[28rem] overflow-y-auto">
                    @foreach ($dueTodayCards as $card)
                        <a href="{{ route('boards.show', $card->board_id) }}" class="flex items-start justify-between gap-3 p-4 hover:bg-red-50/40">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-brand-ink truncate">{{ $card->title }}</p>
                                <p class="text-xs text-steel mt-0.5 truncate">{{ $card->board?->name }}@if($card->empresa) · {{ $card->empresa->corporate_name }}@endif</p>
                                @if ($card->assignee)
                                    <p class="text-xs text-steel mt-0.5"><i class="fa-regular fa-user mr-1"></i>{{ $card->assignee->name }}</p>
                                @endif
                            </div>
                            <x-badge :variant="$card->priority->badgeVariant()" class="shrink-0">{{ $card->priority->label() }}</x-badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Coluna 2: quadros com acesso --}}
        <div class="bg-white border border-hairline rounded-xl overflow-hidden flex flex-col">
            <div class="p-4 border-b border-hairline shrink-0">
                <h3 class="font-semibold text-brand-ink">Meus quadros</h3>
            </div>

            @if ($boards->isEmpty())
                <x-empty-state icon="fa-table-columns" title="Nenhum quadro disponível"
                               message="Você ainda não tem acesso a nenhum quadro. Contate um administrador." />
            @else
                <div class="divide-y divide-hairline max-h-[28rem] overflow-y-auto">
                    @foreach ($boards as $board)
                        <a href="{{ route('boards.show', $board) }}" class="flex items-center gap-3 p-4 hover:bg-surface/60">
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-white shrink-0" style="background: {{ $board->color }}">
                                <i class="fa-solid {{ $board->icon ?? 'fa-table-columns' }} text-sm"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-brand-ink truncate">{{ $board->name }}</p>
                                <p class="text-xs text-steel">{{ $board->setor?->nome ?? 'Sem setor' }} · {{ $board->cards_count }} cards</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Coluna 3: atualizados recentemente --}}
        <div class="bg-white border border-hairline rounded-xl overflow-hidden flex flex-col">
            <div class="p-4 border-b border-hairline shrink-0">
                <h3 class="font-semibold text-brand-ink">Atualizados recentemente</h3>
            </div>

            @if ($recentCards->isEmpty())
                <x-empty-state icon="fa-clipboard-list" title="Nenhum card ainda" message="Os cards atualizados aparecerão aqui." />
            @else
                <div class="divide-y divide-hairline max-h-[28rem] overflow-y-auto">
                    @foreach ($recentCards as $card)
                        <a href="{{ route('boards.show', $card->board_id) }}" class="block p-4 hover:bg-surface/60">
                            <p class="text-sm font-medium text-brand-ink truncate">{{ $card->title }}</p>
                            <p class="text-xs text-steel mt-0.5">{{ $card->board?->name }} @if($card->empresa) · {{ $card->empresa->corporate_name }} @endif</p>
                            <p class="text-xs text-steel mt-0.5">
                                <i class="fa-regular fa-clock mr-1"></i>{{ $card->updated_at->diffForHumans() }}
                                @if ($card->assignee) · {{ $card->assignee->name }} @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
