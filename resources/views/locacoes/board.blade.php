<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Gestão de Locações</h2>
    </x-slot>

    <div x-data="rentalsBoard({
            urls: {
                board: '{{ route('rentals.board') }}',
                ordersStore: '{{ route('rentals.orders.store') }}',
                pedidoBase: '{{ url('locacoes/pedidos') }}',
                linhaBase: '{{ url('locacoes/itens-pedido') }}',
                custoBase: '{{ url('locacoes/custos') }}',
                pagamentoBase: '{{ url('locacoes/pagamentos') }}',
                recebimentoBase: '{{ url('locacoes/recebimentos') }}',
                availability: '{{ route('rentals.availability') }}',
            },
            columns: {{ Illuminate\Support\Js::from($columns) }},
            items: {{ Illuminate\Support\Js::from($items) }},
            statusLabels: {{ Illuminate\Support\Js::from(\App\Domain\Enums\RentalOrderStatus::options()) }},
            initialSearch: {{ Illuminate\Support\Js::from($search) }},
            initialEmpresaId: {{ Illuminate\Support\Js::from($empresaId) }},
            initialShowCancelled: {{ Illuminate\Support\Js::from($showCancelled) }},
            canManage: {{ Illuminate\Support\Js::from($canManage) }},
            initialOpenOrderId: {{ Illuminate\Support\Js::from(request('abrir')) }},
         })" x-cloak>

        {{-- Barra de filtros --}}
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <h1 class="text-xl font-semibold text-brand-ink truncate">Painel de Locações</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:ml-auto">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-xs"></i>
                    <input type="text" x-model="search" @keydown.enter="reload()" placeholder="Código, cliente, local"
                           class="pl-8 h-9 w-56 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                </div>
                <select x-model="empresaId" @change="reload()" class="h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    <option value="">Todas as empresas</option>
                    @foreach ($empresas as $e)
                        <option value="{{ $e->id }}">{{ $e->trade_name ?: $e->corporate_name }}</option>
                    @endforeach
                </select>
                <label class="inline-flex items-center gap-1.5 text-sm text-steel">
                    <input type="checkbox" x-model="showCancelled" @change="reload()" class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                    Mostrar canceladas
                </label>
                <a href="{{ route('rentals.orders.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 h-9 text-sm font-medium text-brand-ink hover:bg-surface">
                    <i class="fa-solid fa-list"></i> Lista
                </a>
                <a href="{{ route('rentals.items.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 h-9 text-sm font-medium text-brand-ink hover:bg-surface">
                    <i class="fa-solid fa-boxes-stacked"></i> Itens
                </a>
                <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 h-9 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                    <i class="fa-solid fa-plus"></i> Novo pedido
                </button>
            </div>
        </div>

        {{-- Colunas do Kanban — mesma moldura dos quadros de processos (borda + divisórias internas). --}}
        <div class="flex gap-4 overflow-x-auto pb-4 items-start">
            <template x-for="col in columns" :key="col.status">
                <div class="shrink-0 w-80 bg-surface rounded-xl border border-hairline flex flex-col max-h-[calc(100vh-14rem)]" :data-column-status="col.status">
                    {{-- Cabeçalho --}}
                    <div class="px-4 py-2.5 flex items-center justify-between border-b border-hairline">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="fa-solid text-sm text-brand-ink" :class="col.icon"></i>
                            <span class="text-sm font-semibold text-brand-ink truncate" x-text="col.label"></span>
                        </div>
                        <span class="text-xs text-steel bg-white border border-hairline rounded-full px-1.5 shrink-0" x-text="col.totals.count"></span>
                    </div>

                    {{-- Total da coluna — linha própria, separada do cabeçalho e da lista de cards --}}
                    <div class="px-4 py-2 text-xs font-medium text-steel border-b border-hairline" x-text="brl(col.totals.revenue_estimated)"></div>

                    {{-- Lista de cards --}}
                    <div class="rental-cards flex-1 overflow-y-auto p-2 space-y-2 min-h-[80px]">
                        <template x-for="card in col.cards" :key="card.id">
                            <div class="rental-card bg-white border border-hairline rounded-lg p-3 cursor-pointer hover:border-brand-orange transition-colors"
                                 :data-card-id="card.id" @click="openOrder(card.id)">
                                <div class="flex items-center justify-between gap-2 pb-1.5 mb-1.5 border-b border-hairline">
                                    <span class="text-xs font-semibold text-brand-ink" x-text="card.code"></span>
                                    <span class="text-xs font-medium text-brand-ink" x-text="brl(card.revenue_estimated)"></span>
                                </div>
                                <p class="text-sm text-brand-ink truncate" x-text="card.client_label || 'Sem cliente'"></p>
                                <p class="text-xs text-steel truncate" x-show="card.location" x-text="card.location"></p>
                                <p class="text-xs text-steel mt-1" x-show="card.period_label" x-text="card.period_label"></p>
                                <div class="flex items-center gap-1.5 mt-2" x-show="card.has_availability_warning || card.has_return_divergence">
                                    <span x-show="card.has_availability_warning" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-brand-orange/15 text-brand-orange-deep">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Falta de itens
                                    </span>
                                    <span x-show="card.has_return_divergence" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-700">
                                        <i class="fa-solid fa-clipboard-check"></i> Divergência
                                    </span>
                                </div>
                            </div>
                        </template>
                        <p x-show="col.cards.length === 0" class="text-xs text-steel text-center py-6">Nenhum pedido aqui.</p>
                    </div>
                </div>
            </template>
        </div>

        {{-- Modal: novo pedido --}}
        <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="createOpen = false"></div>
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl p-6 space-y-4">
                <h3 class="text-base font-semibold text-brand-ink">Novo pedido de locação</h3>
                <div>
                    <label class="text-xs text-steel">Cliente (empresa)</label>
                    <select x-model="newOrder.empresa_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        <option value="">— Selecionar —</option>
                        @foreach ($empresas as $e)
                            <option value="{{ $e->id }}">{{ $e->trade_name ?: $e->corporate_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-steel">Ou cliente avulso (nome)</label>
                    <input type="text" x-model="newOrder.client_name" placeholder="Nome do cliente"
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
                <div>
                    <label class="text-xs text-steel">Local / evento</label>
                    <input type="text" x-model="newOrder.location"
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-steel">Retirada</label>
                        <input type="datetime-local" x-model="newOrder.start_at"
                               class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-steel">Devolução</label>
                        <input type="datetime-local" x-model="newOrder.end_at"
                               class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="createOpen = false" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                    <button type="button" @click="createOrder()" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Criar
                    </button>
                </div>
            </div>
        </div>

        @include('locacoes.partials._drawer')
    </div>
</x-app-layout>
