@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Custos — {{ $evento->name }}</h2></x-slot>

    <x-page-header title="Custos" :subtitle="$evento->name" icon="fa-arrow-trend-down">
        <x-slot name="actions">
            <a href="{{ route('finance.export', $evento) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-file-excel"></i> Exportar
            </a>
            <a href="{{ route('finance.show', $evento) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-chart-pie"></i> Resumo
            </a>
        </x-slot>
    </x-page-header>

    @include('financeiro.eventos._tabs', ['active' => 'custos'])

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Filtros server-side --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <div class="flex-1 min-w-[180px]">
            <label class="text-xs text-steel">Buscar descrição</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
        </div>
        <div class="w-44">
            <label class="text-xs text-steel">Item</label>
            <x-form.select name="categoria_id" class="mt-1">
                <option value="">Todos</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected(request('categoria_id') == $categoria->id)>{{ $categoria->nome }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div class="w-48">
            <label class="text-xs text-steel">Status</label>
            <x-form.select name="status" class="mt-1">
                <option value="">Todos</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </x-form.select>
        </div>
        {{-- Mesma lista do select "Empresa" de cada linha da grade (que, por histórico, é o
             fornecedor vinculado — não há `empresa_id` em FinanceCostItem). Seleção múltipla por
             checkbox: `fornecedor_id[]` chega como array, igual ao padrão já usado em
             RentalOrderController para filtros de múltiplos valores num GET simples. O estado
             marcado é 100% server-rendered (@checked) — o x-data local só abre/fecha o painel e
             filtra a lista por texto; não depende do x-data maior da grade (financeCosts), que só
             começa no <div> de baixo. --}}
        <div class="w-48" x-data="{ open: false, search: '' }" @click.outside="open = false">
            <label class="text-xs text-steel">Empresa</label>
            <div class="relative mt-1">
                <button type="button" @click="open = !open; search = ''"
                        class="w-full h-9 flex items-center justify-between gap-2 rounded-md border border-gray-300 px-3 text-sm text-left hover:border-brand-orange transition-colors bg-white">
                    @php($selecionados = (array) request('fornecedor_id', []))
                    <span class="truncate {{ count($selecionados) > 0 ? 'text-brand-ink' : 'text-steel' }}">
                        {{ count($selecionados) > 0 ? count($selecionados).' selecionada(s)' : 'Todas' }}
                    </span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-steel shrink-0"></i>
                </button>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="absolute left-0 z-30 mt-2 w-64 bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-2">
                    <div class="relative mb-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                        <input type="text" x-model="search" placeholder="Pesquisar empresa"
                               class="w-full pl-7 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    </div>
                    <div class="max-h-48 overflow-y-auto space-y-0.5">
                        @foreach ($fornecedores as $fornecedor)
                            <label x-show="search === '' || {{ Illuminate\Support\Js::from(mb_strtolower($fornecedor->name)) }}.includes(search.toLowerCase())"
                                   class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm cursor-pointer">
                                <input type="checkbox" name="fornecedor_id[]" value="{{ $fornecedor->id }}"
                                       @checked(in_array($fornecedor->id, $selecionados))
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                <span class="flex-1 truncate text-brand-ink">{{ $fornecedor->name }}</span>
                            </label>
                        @endforeach
                        <p x-show="search !== '' && {{ Illuminate\Support\Js::from($fornecedores->pluck('name')->map(fn ($n) => mb_strtolower($n))) }}.every(n => !n.includes(search.toLowerCase()))"
                           class="text-xs text-steel px-2 py-1.5">Nenhuma empresa encontrada.</p>
                    </div>
                    @if (count($selecionados) > 0)
                        <button type="button" @click="$el.closest('form').querySelectorAll('input[name=\'fornecedor_id[]\']').forEach(el => el.checked = false)"
                                class="w-full text-left px-2 py-1.5 mt-1 text-xs text-red-600 hover:underline border-t border-hairline">Limpar seleção</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="w-44">
            <label class="text-xs text-steel">Grupo de pagamento</label>
            <x-form.select name="source_id" class="mt-1">
                <option value="">Todos</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->id }}" @selected(request('source_id') == $source->id)>{{ $source->name }}</option>
                @endforeach
            </x-form.select>
        </div>
        {{-- Fora do x-data="financeCosts(...)" (esse div só abre mais abaixo) — a mesma máscara
             usada nos valores do card/Financeiro, exposta em window.maskMoneyDigits (ver app.js)
             para funcionar aqui também, sem depender do componente da grade. --}}
        <div class="w-40" x-data="{ value: @js(request('total_actual', '')) }">
            <label class="text-xs text-steel">Total real.</label>
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
                <input type="text" name="total_actual" inputmode="decimal" :value="value"
                       @input="value = window.maskMoneyDigits($event.target.value)"
                       placeholder="0,00" class="w-full pl-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>
        </div>
        <label class="inline-flex items-center gap-1.5 text-xs text-steel pb-2">
            <input type="checkbox" name="sem_comprovante" value="1" @checked(request('sem_comprovante'))
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"> Sem comprovante
        </label>
        <label class="inline-flex items-center gap-1.5 text-xs text-steel pb-2">
            <input type="checkbox" name="falta_pagar" value="1" @checked(request('falta_pagar'))
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"> Falta pagar
        </label>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
        @if (request()->hasAny(['search', 'categoria_id', 'status', 'fornecedor_id', 'total_actual', 'source_id', 'sem_comprovante', 'falta_pagar', 'pago_a_maior']))
            <a href="{{ route('finance.costs.index', $evento) }}" class="text-sm text-steel hover:text-brand-ink pb-2">Limpar</a>
        @endif
    </form>

    <div x-data="financeCosts({
            urls: {
                costBase: '{{ url('financeiro/custos') }}',
                costStore: '{{ route('finance.costs.store', $evento) }}',
                costBulk: '{{ route('finance.costs.bulk', $evento) }}',
                documentBase: '{{ url('financeiro/documentos') }}',
                paymentBase: '{{ url('financeiro/pagamentos') }}',
                boardCardBase: '{{ url('quadros') }}',
            },
            rows: {{ Illuminate\Support\Js::from($rows) }},
            presets: {{ Illuminate\Support\Js::from($presets) }},
            readonly: {{ Illuminate\Support\Js::from($sheet->isClosed()) }},
            usesSecondEstimate: {{ Illuminate\Support\Js::from($sheet->uses_second_estimate) }},
         })">

        {{-- Toggles de grupo de coluna + ações em massa --}}
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="text-xs text-steel">Colunas:</span>
            @foreach ([
                'estimate1' => 'Previsto 1',
                'estimate2' => 'Previsto 2',
                'actual' => 'Realizado',
                'payments' => 'Pagamentos',
                'control' => 'Controle',
            ] as $key => $label)
                @if ($key !== 'estimate2' || $sheet->uses_second_estimate)
                    <button type="button" @click="groups.{{ $key }} = !groups.{{ $key }}"
                            class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                            :class="groups.{{ $key }} ? 'border-brand-orange bg-brand-orange/10 text-brand-orange-deep' : 'border-hairline text-steel'">
                        {{ $label }}
                    </button>
                @endif
            @endforeach

            <div class="ml-auto flex items-center gap-2" x-show="selected.length > 0" x-cloak>
                <span class="text-xs text-steel"><span x-text="selected.length"></span> selecionada(s)</span>
                <select @change="bulk('status', $event.target.value); $event.target.value = ''"
                        class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    <option value="">Alterar status…</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select @change="bulk('art_status', $event.target.value); $event.target.value = ''"
                        class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    <option value="">Alterar ART…</option>
                    @foreach ($artStatuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @unless ($sheet->isClosed())
                    <button type="button" @click="bulk('delete')" class="text-xs text-red-600 hover:underline">
                        <i class="fa-solid fa-trash"></i> Excluir
                    </button>
                @endunless
            </div>
        </div>

        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border-collapse">
                    <thead class="bg-surface text-left text-steel">
                        <tr class="text-xs uppercase tracking-wide">
                            <th class="px-2 py-2.5 w-8 sticky left-0 bg-surface z-10">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll()"
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                            </th>
                            <th class="px-2 py-2.5 font-medium sticky left-8 bg-surface z-10 min-w-[10rem]">Item</th>
                            <th class="px-2 py-2.5 font-medium min-w-[16rem]">Descrição</th>
                            <th class="px-2 py-2.5 font-medium min-w-[11rem]">Status</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.estimate1">Vlr. unit.</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.estimate1">Total prev.</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.estimate2" x-cloak>Vlr. unit. 2</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.estimate2" x-cloak>Total prev. 2</th>
                            <th class="px-2 py-2.5 font-medium w-36 text-right" x-show="groups.actual">Vlr. unit. real.</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.actual">Total real.</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.payments">Pago</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.payments">Falta pagar</th>
                            <th class="px-2 py-2.5 font-medium min-w-[9rem]">ART</th>
                            <th class="px-2 py-2.5 font-medium min-w-[12rem]">Empresa</th>
                            <th class="px-2 py-2.5 font-medium min-w-[11rem]">Autorizado por</th>
                            <th class="px-2 py-2.5 font-medium w-20 text-right">Diárias</th>
                            <th class="px-2 py-2.5 font-medium w-20 text-right">Quant.</th>
                            <th class="px-2 py-2.5 font-medium w-24" x-show="groups.control">Controle</th>
                            <th class="px-2 py-2.5 w-16"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-hairline">
                        <template x-for="row in rows" :key="row.id">
                            <tr :id="`linha-${row.id}`" class="hover:bg-surface/60 align-middle"
                                :class="savedIds.includes(row.id) && 'bg-green-50'">
                                <td class="px-2 py-1.5 sticky left-0 bg-white z-10">
                                    <input type="checkbox" :value="row.id" x-model.number="selected"
                                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                </td>

                                <td class="px-2 py-1.5 sticky left-8 bg-white z-10">
                                    <select x-model="row.fornecedor_categoria_id" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        <option value="">—</option>
                                        @foreach ($categorias as $categoria)
                                            <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="px-2 py-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" data-field="description" x-model="row.description" @input="touch(row)"
                                               :disabled="readonly" :list="`presets-${row.fornecedor_categoria_id || 'none'}`"
                                               class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        {{-- Origem no Kanban: a linha nasceu de um card (specs/23 §6). Número e ícone
                                             abrem o mesmo link (specs/18) — clicar em qualquer um dos dois abre o
                                             quadro em nova aba, já com o modal do card aberto. --}}
                                        <a x-show="row.card_id" x-cloak :href="cardUrl(row)" target="_blank" rel="noopener"
                                           :title="`Abrir o card #${row.card_id} em nova aba`"
                                           class="shrink-0 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-brand-orange/15 text-brand-orange-deep hover:bg-brand-orange/25">
                                            <span x-text="`#${row.card_id}`"></span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                        </a>
                                        <i class="fa-solid fa-circle-notch fa-spin text-[10px] text-steel"
                                           x-show="savingIds.includes(row.id)" x-cloak></i>
                                    </div>
                                </td>

                                <td class="px-2 py-1.5">
                                    <select x-model="row.status" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="px-2 py-1.5" x-show="groups.estimate1">
                                    <input type="text" inputmode="decimal" x-model="row.unit_estimated_1" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5 text-right text-xs text-brand-ink whitespace-nowrap" x-show="groups.estimate1"
                                    x-text="brl(row.total_estimated_1)"></td>

                                <td class="px-2 py-1.5" x-show="groups.estimate2" x-cloak>
                                    <input type="text" inputmode="decimal" x-model="row.unit_estimated_2" @input="touch(row)" :disabled="readonly"
                                           placeholder="—"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5 text-right text-xs text-brand-ink whitespace-nowrap" x-show="groups.estimate2" x-cloak
                                    x-text="row.unit_estimated_2 === null || row.unit_estimated_2 === '' ? '—' : brl(row.total_estimated_2)"></td>

                                <td class="px-2 py-1.5" x-show="groups.actual">
                                    {{-- Mesma máscara dos valores do card/Financeiro (dígito digitado = centavo):
                                         `row.unit_actual` já nasce em string BR (ver normalizeCostRow em finance.js),
                                         então aqui é só espelhar — nunca reconverter o valor em cada tecla. --}}
                                    <input type="text" inputmode="decimal" :value="row.unit_actual"
                                           @input="row.unit_actual = maskMoneyDigits($event.target.value); touch(row)"
                                           :disabled="readonly" placeholder="Não realizado"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5 text-right text-xs font-medium text-brand-ink whitespace-nowrap" x-show="groups.actual"
                                    x-text="row.unit_actual === '' ? '—' : brl(row.total_actual)"></td>

                                <td class="px-2 py-1.5 text-right whitespace-nowrap" x-show="groups.payments">
                                    <button type="button" @click="openPayments(row)"
                                            class="text-xs text-brand-ink hover:text-brand-orange-deep hover:underline"
                                            x-text="brl(row.paid)"></button>
                                </td>
                                {{-- "pago a mais" vira tooltip nativo (title), não mais texto ao lado do número —
                                     só aparece passando o mouse, sem disputar espaço com o valor. --}}
                                <td class="px-2 py-1.5 text-right text-xs whitespace-nowrap" x-show="groups.payments"
                                    :class="row.pending > 0 ? 'text-brand-orange-deep' : (row.pending < 0 ? 'text-red-600' : 'text-steel')"
                                    :title="row.pending < 0 ? 'Pago a mais' : null"
                                    x-text="brl(row.pending)"></td>

                                <td class="px-2 py-1.5">
                                    <select x-model="row.art_status" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        @foreach ($artStatuses as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="px-2 py-1.5">
                                    <select x-model="row.fornecedor_id" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        <option value="">—</option>
                                        @foreach ($fornecedores as $fornecedor)
                                            <option value="{{ $fornecedor->id }}">{{ $fornecedor->name }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="px-2 py-1.5">
                                    <select x-model="row.authorized_by" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        <option value="">—</option>
                                        @foreach ($usuarios as $usuario)
                                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="px-2 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.daily_count" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.quantity" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>

                                <td class="px-2 py-1.5" x-show="groups.control" x-data="{ controlOpen: false }" @click.outside="controlOpen = false">
                                    {{-- Os ícones inline viravam uma fileira de até 9 botões por linha — trocado
                                         por um único botão-resumo que abre um menu com a lista completa (ícone +
                                         nome + número), reduzindo o que a grade mostra sem esconder informação. --}}
                                    <div class="relative inline-block">
                                        <button type="button" @click="controlOpen = !controlOpen"
                                                class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md border text-xs font-medium transition-colors"
                                                :class="row.documents.some(c => c.proof && c.count === 0)
                                                    ? 'border-brand-orange/40 bg-brand-orange/5 text-brand-orange-deep'
                                                    : 'border-hairline text-steel hover:border-brand-orange hover:text-brand-orange-deep'">
                                            <i class="fa-solid fa-folder-open"></i>
                                            <span x-text="row.documents.filter(c => c.proof || c.count > 0).length"></span>
                                        </button>
                                        <div x-show="controlOpen" x-cloak x-transition
                                             class="absolute left-0 z-20 mt-1 w-56 bg-white border border-hairline rounded-md shadow-lg p-1.5 space-y-0.5">
                                            {{-- Os seis que provam a despesa aparecem sempre (cinza = pendência real).
                                                 Geral, Minuta e Recibo vêm do card com o próprio tipo, mas a ausência
                                                 deles não é pendência — só aparecem quando existem. --}}
                                            <template x-for="chip in row.documents.filter(c => c.proof || c.count > 0)" :key="chip.kind">
                                                <button type="button" @click="openDocuments(row, chip.kind); controlOpen = false"
                                                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-xs text-left"
                                                        :class="chip.count > 0 ? 'text-green-700' : 'text-steel'">
                                                    <i class="fa-solid w-4 text-center shrink-0" :class="chip.icon"></i>
                                                    <span class="flex-1 truncate" x-text="`${chip.label} (${chip.count})`"></span>
                                                </button>
                                            </template>
                                            <p x-show="row.documents.filter(c => c.proof || c.count > 0).length === 0"
                                               class="text-xs text-steel px-2 py-1.5">Nenhum documento.</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-2 py-1.5 text-right whitespace-nowrap">
                                    <button type="button" @click="duplicate(row)" :disabled="readonly" title="Duplicar linha"
                                            class="text-steel hover:text-brand-ink disabled:opacity-30">
                                        <i class="fa-solid fa-copy text-xs"></i>
                                    </button>
                                    <button type="button" @click="remove(row)" :disabled="readonly" title="Excluir linha"
                                            class="ml-1 text-steel hover:text-red-600 disabled:opacity-30">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="rows.length === 0">
                            <td colspan="19" class="px-4 py-10 text-center text-sm text-steel">
                                Nenhuma linha de custo. Envie um card do Kanban para o Financeiro ou adicione uma linha abaixo.
                            </td>
                        </tr>
                    </tbody>

                    {{-- Rodapé: totais do FILTRO aplicado, calculados pelo servidor (colunas geradas). --}}
                    <tfoot class="bg-surface text-xs font-semibold text-brand-ink">
                        <tr>
                            <td class="px-2 py-2.5 sticky left-0 bg-surface z-10"></td>
                            <td class="px-2 py-2.5 sticky left-8 bg-surface z-10">TOTAL</td>
                            <td class="px-2 py-2.5 text-steel font-normal">{{ $footer->total }} linha(s)</td>
                            <td></td>
                            <td class="px-2 py-2.5" x-show="groups.estimate1"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.estimate1">{{ $money($footer->e1) }}</td>
                            <td class="px-2 py-2.5" x-show="groups.estimate2" x-cloak></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.estimate2" x-cloak>{{ $money($footer->e2) }}</td>
                            <td class="px-2 py-2.5" x-show="groups.actual"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.actual">{{ $money($footer->act) }}</td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.payments">{{ $money($paidTotal) }}</td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.payments">{{ $money($footer->act - $paidTotal) }}</td>
                            <td colspan="5"></td>
                            <td x-show="groups.control"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @unless ($sheet->isClosed())
                <div class="p-3 border-t border-hairline">
                    <button type="button" @click="addRow()"
                            class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Adicionar linha
                    </button>
                </div>
            @endunless

            @if ($items->hasPages())
                <div class="px-4 py-3 border-t border-hairline">{{ $items->links() }}</div>
            @endif
        </div>

        {{-- Datalists de descrição por categoria (autocomplete dos 168 itens do modelo) --}}
        @foreach ($presets as $categoriaId => $descriptions)
            <datalist id="presets-{{ $categoriaId }}">
                @foreach ($descriptions as $description)
                    <option value="{{ $description }}"></option>
                @endforeach
            </datalist>
        @endforeach

        @include('financeiro.eventos._drawer', ['sheet' => $sheet, 'sources' => $sources])
    </div>
</x-app-layout>
