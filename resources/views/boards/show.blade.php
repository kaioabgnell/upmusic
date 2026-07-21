<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-md text-white" style="background: {{ $board->color }}">
                <i class="fa-solid {{ $board->icon ?? 'fa-table-columns' }} text-xs"></i>
            </span>
            <h2 class="text-lg font-semibold text-brand-ink">{{ $board->name }}</h2>
        </div>
    </x-slot>

    <div x-data="kanban({
            urls: {
                cardStore: '{{ route('cards.store', $board) }}',
                cardBase: '{{ url('cards') }}',
                anexoBase: '{{ url('anexos') }}',
                empresaQuick: '{{ route('empresas.quick') }}',
                fornecedorQuick: '{{ route('fornecedores.quick') }}',
                fornecedorPriceHistory: '{{ url('fornecedores') }}',
                kanbanData: '{{ route('boards.kanban.data', $board) }}',
            },
            initialFilters: {{ Illuminate\Support\Js::from($filters) }},
            columns: {{ Illuminate\Support\Js::from($columns) }},
            fields: {{ Illuminate\Support\Js::from($fields) }},
            empresas: {{ Illuminate\Support\Js::from($empresas) }},
            fornecedores: {{ Illuminate\Support\Js::from($fornecedores) }},
            events: {{ Illuminate\Support\Js::from($events) }},
            assignees: {{ Illuminate\Support\Js::from($assignees) }},
            transferBoards: {{ Illuminate\Support\Js::from($transferBoards) }},
         })">

        {{-- Barra de filtros --}}
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <h1 class="text-xl font-semibold text-brand-ink truncate">{{ $board->name }}</h1>
                <span class="text-sm text-steel hidden sm:inline truncate">{{ $board->setor?->nome }}</span>
            </div>

            {{-- Alternância Kanban / Lista --}}
            <div class="inline-flex items-center rounded-md border border-hairline p-0.5 shrink-0">
                <button type="button" @click="viewMode = 'kanban'" class="inline-flex items-center gap-1.5 rounded px-3 h-8 text-sm font-medium transition-colors" :class="viewMode === 'kanban' ? 'bg-brand-orange text-brand-ink' : 'text-steel hover:text-brand-ink'">
                    <i class="fa-solid fa-table-columns"></i> Kanban
                </button>
                <button type="button" @click="viewMode = 'lista'" class="inline-flex items-center gap-1.5 rounded px-3 h-8 text-sm font-medium transition-colors" :class="viewMode === 'lista' ? 'bg-brand-orange text-brand-ink' : 'text-steel hover:text-brand-ink'">
                    <i class="fa-solid fa-list"></i> Lista
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:ml-auto">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-xs"></i>
                        <input type="text" x-model="search" @input.debounce.400ms="reloadCards()" placeholder="Buscar card" class="pl-8 h-9 w-40 sm:w-52 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    </div>

                    {{-- Filtros agrupados num popover — evita quebra de layout com muitos selects na barra --}}
                    <div x-data="{ filtersOpen: false }" class="relative">
                        <button type="button" @click="filtersOpen = !filtersOpen"
                                class="h-9 inline-flex items-center gap-2 rounded-md border px-3 text-sm font-medium transition-colors"
                                :class="filtersOpen || activeFilterCount > 0 ? 'border-brand-orange text-brand-ink bg-brand-orange/5' : 'border-hairline text-brand-ink hover:bg-surface'">
                            <i class="fa-solid fa-sliders"></i> Filtros
                            <span x-show="activeFilterCount > 0" class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-brand-orange text-brand-ink text-[10px] font-bold" x-text="activeFilterCount"></span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="filtersOpen && 'rotate-180'"></i>
                        </button>

                        <div x-show="filtersOpen" x-cloak @click.outside="filtersOpen = false"
                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="absolute right-0 lg:left-0 mt-2 w-72 bg-white border border-hairline rounded-xl shadow-lg z-30 origin-top-right">
                            <div class="p-4 space-y-4">
                                <div>
                                    <label class="text-xs font-medium text-steel">Empresa</label>
                                    <select x-model="filters.empresa_id" @change="reloadCards()" class="mt-1 w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                        <option value="">Todas</option>
                                        @foreach ($empresas as $e)
                                            <option value="{{ $e->id }}">{{ $e->corporate_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-steel">Evento</label>
                                    <select x-model="filters.event_id" @change="reloadCards()" class="mt-1 w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                        <option value="">Todos</option>
                                        @foreach ($events as $ev)
                                            <option value="{{ $ev->id }}">{{ $ev->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-steel">Responsável</label>
                                    <select x-model="filters.assignee_id" @change="reloadCards()" class="mt-1 w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                        <option value="">Todos</option>
                                        @foreach ($assignees as $a)
                                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-steel">Prioridade</label>
                                    <select x-model="filters.priority" @change="reloadCards()" class="mt-1 w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                        <option value="">Todas</option>
                                        @foreach (['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'] as $v => $l)
                                            <option value="{{ $v }}">{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div x-show="activeFilterCount > 0" class="border-t border-hairline p-3">
                                <button type="button" @click="clearFilters(); filtersOpen = false" class="w-full flex items-center justify-center gap-1.5 text-xs font-medium text-steel hover:text-red-600">
                                    <i class="fa-solid fa-xmark"></i> Limpar todos os filtros
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ações do quadro (separadas dos filtros) --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if ($boardTemplates->isNotEmpty())
                        <button type="button" onclick="importBoardTemplate()" title="Importar template" class="h-9 inline-flex items-center gap-1 rounded-md border border-hairline px-3 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-file-import"></i> <span class="hidden xl:inline">Importar template</span></button>
                    @endif
                    @can('configure', $board)
                        <a href="{{ route('external.forms.manage', $board) }}" title="Compartilhar formulário" class="h-9 inline-flex items-center gap-1 rounded-md border border-hairline px-3 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-share-nodes"></i> <span class="hidden xl:inline">Compartilhar formulário</span></a>
                        <a href="{{ route('boards.config', $board) }}" title="Configurar" class="h-9 inline-flex items-center gap-1 rounded-md border border-hairline px-3 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-gear"></i> <span class="hidden xl:inline">Configurar</span></a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Erro ao carregar os cards --}}
        <div x-show="loadError" x-cloak class="mb-4 rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700 flex items-center justify-between gap-3">
            <span><i class="fa-solid fa-triangle-exclamation mr-2"></i>Não foi possível carregar os cards deste quadro.</span>
            <button type="button" @click="fetchCards()" class="shrink-0 rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">Tentar novamente</button>
        </div>

        {{-- Colunas --}}
        <div x-show="viewMode === 'kanban'" class="flex gap-4 overflow-x-auto pb-4 items-start">
            <template x-for="column in columns" :key="column.id">
                <div class="shrink-0 w-72 bg-surface rounded-xl border border-hairline flex flex-col max-h-[calc(100vh-14rem)]" :data-column-id="column.id">
                    <div class="flex items-center justify-between px-3 py-2.5 border-b border-hairline">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0" :style="`background: ${column.color || '#c7c7c7'}`"></span>
                            <span class="text-sm font-semibold text-brand-ink truncate" x-text="column.name"></span>
                            <span class="text-xs text-steel bg-white border border-hairline rounded-full px-1.5" x-text="column.cards.length"></span>
                            <i x-show="column.is_entry" class="fa-solid fa-arrow-right-to-bracket text-brand-orange text-xs" title="Entrada"></i>
                            <i x-show="column.is_final" class="fa-solid fa-flag-checkered text-brand-ink text-xs" title="Final"></i>
                        </div>
                        <button type="button" @click="openCreate(column.id)" class="text-steel hover:text-brand-orange-deep" title="Novo card"><i class="fa-solid fa-plus"></i></button>
                    </div>

                    <div class="kanban-cards flex-1 overflow-y-auto p-2 space-y-2 min-h-[60px]">
                        <div x-show="loadingCards" class="space-y-2">
                            <div class="h-24 rounded-lg bg-gray-100 animate-pulse"></div>
                            <div class="h-24 rounded-lg bg-gray-100 animate-pulse"></div>
                        </div>
                        <template x-for="card in column.cards" :key="card.id">
                            <div class="kanban-card bg-white rounded-lg p-3 cursor-pointer hover:border-brand-orange transition-colors"
                                 :class="dueBorderClass(card)"
                                 :data-card-id="card.id" @click="openCard(card.id)"
                                 :data-tooltip="dueTooltipText(card)"
                                 @mouseenter="dueTooltipText(card) && showTooltip($event)" @mouseleave="hideTooltip()">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-brand-ink leading-snug" x-text="card.title"></p>
                                    <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full" :class="priorityMeta(card.priority).classes" x-text="priorityMeta(card.priority).label"></span>
                                </div>
                                <p x-show="card.empresa" class="text-xs text-steel mt-1.5"><i class="fa-solid fa-building mr-1"></i><span x-text="truncate(card.empresa, 28)"></span></p>
                                <p x-show="card.event" class="text-xs text-steel mt-1"><i class="fa-solid fa-calendar-days mr-1"></i><span x-text="truncate(card.event, 28)"></span></p>
                                <div class="flex items-center justify-between mt-2.5 text-xs text-steel">
                                    <div class="flex items-center gap-2">
                                        <span x-show="card.due_date" :class="dueDateClass(card)"><i class="fa-regular fa-calendar mr-0.5"></i><span x-text="card.due_date"></span></span>
                                        <span x-show="card.attachments_count"><i class="fa-solid fa-paperclip"></i> <span x-text="card.attachments_count"></span></span>
                                        <span x-show="card.comments_count"><i class="fa-regular fa-comment"></i> <span x-text="card.comments_count"></span></span>
                                    </div>
                                    <template x-if="card.assignee">
                                        <span>
                                            <template x-if="card.assignee_avatar_url">
                                                <img :src="card.assignee_avatar_url" :alt="card.assignee" :title="card.assignee" class="w-6 h-6 rounded-full object-cover shrink-0">
                                            </template>
                                            <template x-if="!card.assignee_avatar_url">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand-ink text-white text-[10px] font-semibold" :title="card.assignee" x-text="card.assignee_initial"></span>
                                            </template>
                                        </span>
                                    </template>
                                </div>
                                <p x-show="card.estimated_value !== null" class="text-xs font-medium text-brand-ink mt-2">R$ <span x-text="formatMoneyBR(card.estimated_value)"></span></p>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="openCreate(column.id)" class="m-2 mt-0 flex items-center justify-center gap-2 rounded-md border border-dashed border-hairline py-2 text-xs font-medium text-steel hover:border-brand-orange hover:text-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Criar card
                    </button>
                </div>
            </template>

            @can('configure', $board)
                <a href="{{ route('boards.config', $board) }}" class="shrink-0 w-72 flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-hairline py-3 text-sm font-medium text-steel hover:border-brand-orange hover:text-brand-orange-deep">
                    <i class="fa-solid fa-plus"></i> Adicionar nova coluna
                </a>
            @endcan
        </div>

        {{-- Lista --}}
        <div x-show="viewMode === 'lista'" x-cloak class="pb-4">
            <div x-show="!loadingCards && flatCards.length === 0">
                <div class="bg-white border border-hairline rounded-xl">
                    <x-empty-state icon="fa-list" title="Nenhum card encontrado" message="Ajuste os filtros ou crie um novo card no quadro." />
                </div>
            </div>
            <div x-show="loadingCards || flatCards.length > 0">
                <x-data-table>
                    <x-slot name="head">
                        <th class="px-4 py-3 font-medium">Título</th>
                        <th class="px-4 py-3 font-medium">Etapa</th>
                        <th class="px-4 py-3 font-medium">Empresa</th>
                        <th class="px-4 py-3 font-medium">Evento</th>
                        <th class="px-4 py-3 font-medium">Responsável</th>
                        <th class="px-4 py-3 font-medium">Prioridade</th>
                        <th class="px-4 py-3 font-medium">Prazo</th>
                        <th class="px-4 py-3 font-medium">Valor previsto</th>
                        <th class="px-4 py-3 font-medium text-right">Anexos / Comentários</th>
                    </x-slot>

                    <template x-if="loadingCards">
                        <tr>
                            <td class="px-4 py-3" colspan="9">
                                <div class="h-5 rounded bg-gray-100 animate-pulse"></div>
                            </td>
                        </tr>
                    </template>
                    <template x-for="card in flatCards" :key="card.id">
                        <tr class="hover:bg-surface/60 cursor-pointer" @click="openCard(card.id)">
                            <td class="px-4 py-3 font-medium text-brand-ink" x-text="card.title"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-steel">
                                    <span class="w-2 h-2 rounded-full shrink-0" :style="`background: ${card.column_color || '#c7c7c7'}`"></span>
                                    <span x-text="card.column_name"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-steel" x-text="card.empresa || '—'"></td>
                            <td class="px-4 py-3 text-steel" x-text="card.event || '—'"></td>
                            <td class="px-4 py-3">
                                <template x-if="card.assignee">
                                    <span class="inline-flex items-center gap-1.5 text-steel">
                                        <template x-if="card.assignee_avatar_url">
                                            <img :src="card.assignee_avatar_url" :alt="card.assignee" class="w-5 h-5 rounded-full object-cover shrink-0">
                                        </template>
                                        <template x-if="!card.assignee_avatar_url">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-brand-ink text-white text-[9px] font-semibold shrink-0" x-text="card.assignee_initial"></span>
                                        </template>
                                        <span x-text="card.assignee"></span>
                                    </span>
                                </template>
                                <span x-show="!card.assignee" class="text-steel">—</span>
                            </td>
                            <td class="px-4 py-3"><span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" :class="priorityMeta(card.priority).classes" x-text="priorityMeta(card.priority).label"></span></td>
                            <td class="px-4 py-3">
                                <template x-if="card.due_date">
                                    <div class="flex items-center gap-1.5">
                                        <span :class="dueDateClass(card) || 'text-steel'" x-text="card.due_date"></span>
                                        <span x-show="dueBadgeMeta(card)" :class="badgeClasses(dueBadgeMeta(card)?.variant)" x-text="dueBadgeMeta(card)?.label"></span>
                                    </div>
                                </template>
                                <span x-show="!card.due_date" class="text-steel">—</span>
                            </td>
                            <td class="px-4 py-3 text-steel" x-text="card.estimated_value !== null ? ('R$ ' + formatMoneyBR(card.estimated_value)) : '—'"></td>
                            <td class="px-4 py-3 text-right text-steel">
                                <span x-show="card.attachments_count" class="mr-2"><i class="fa-solid fa-paperclip"></i> <span x-text="card.attachments_count"></span></span>
                                <span x-show="card.comments_count"><i class="fa-regular fa-comment"></i> <span x-text="card.comments_count"></span></span>
                            </td>
                        </tr>
                    </template>
                </x-data-table>
            </div>
        </div>

        @include('boards.partials.card-panel')

        {{-- Tooltip flutuante (estilo Bootstrap) para os cards --}}
        <div x-show="tooltip.show" x-cloak x-transition.opacity.duration.150ms
             class="fixed z-50 pointer-events-none"
             :style="`top:${tooltip.y}px; left:${tooltip.x}px; transform: translate(-50%, -100%);`">
            <div class="px-2.5 py-1.5 rounded-md bg-brand-ink text-white text-xs font-medium shadow-lg whitespace-nowrap" x-text="tooltip.text"></div>
            <div class="w-2 h-2 bg-brand-ink rotate-45 mx-auto -mt-1"></div>
        </div>
    </div>

    @push('scripts')
    <script>
        const BOARD_TEMPLATES = @json($boardTemplates);
        const BOARD_EMPRESAS = @json($empresas);
        async function importBoardTemplate() {
            const tplOpts = BOARD_TEMPLATES.map(t => `<option value="${t.id}">${t.name} (${t.items_count} cards)</option>`).join('');
            const empOpts = ['<option value="">— Sem empresa —</option>'].concat(BOARD_EMPRESAS.map(e => `<option value="${e.id}">${e.corporate_name}</option>`)).join('');
            const selectClass = 'block w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md';
            const { value: form } = await window.Swal.fire({
                title: 'Importar template',
                html:
                    `<div class="text-left">
                        <label class="block text-sm font-medium text-brand-ink mb-1">Template</label>
                        <select id="bt-tpl" class="${selectClass}">${tplOpts}</select>
                        <label class="block text-sm font-medium text-brand-ink mb-1 mt-3">Vincular empresa (opcional)</label>
                        <select id="bt-emp" class="${selectClass}">${empOpts}</select>
                    </div>`,
                showCancelButton: true, confirmButtonText: 'Importar', cancelButtonText: 'Cancelar', confirmButtonColor: '#ff8c1e',
                preConfirm: () => ({ tpl: document.getElementById('bt-tpl').value, emp: document.getElementById('bt-emp').value }),
            });
            if (!form) return;
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = `{{ url('templates') }}/${form.tpl}/importar`;
            f.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">` +
                `<input type="hidden" name="board_id" value="{{ $board->id }}">` +
                `<input type="hidden" name="empresa_id" value="${form.emp}">`;
            document.body.appendChild(f);
            f.submit();
        }
    </script>
    @endpush
</x-app-layout>
