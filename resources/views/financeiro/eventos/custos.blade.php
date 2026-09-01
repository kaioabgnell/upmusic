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
        <div class="w-40">
            <label class="text-xs text-steel">ART</label>
            <x-form.select name="art_status" class="mt-1">
                <option value="">Todas</option>
                @foreach ($artStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('art_status') === $value)>{{ $label }}</option>
                @endforeach
            </x-form.select>
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
        @if (request()->hasAny(['search', 'categoria_id', 'status', 'art_status', 'source_id', 'sem_comprovante', 'falta_pagar', 'pago_a_maior']))
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
                            <th class="px-2 py-2.5 font-medium min-w-[9rem]">ART</th>
                            <th class="px-2 py-2.5 font-medium min-w-[12rem]">Empresa</th>
                            <th class="px-2 py-2.5 font-medium min-w-[11rem]">Autorizado por</th>
                            <th class="px-2 py-2.5 font-medium w-20 text-right">Diárias</th>
                            <th class="px-2 py-2.5 font-medium w-20 text-right">Quant.</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.estimate1">Vlr. unit.</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.estimate1">Total prev.</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.estimate2" x-cloak>Vlr. unit. 2</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.estimate2" x-cloak>Total prev. 2</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.actual">Vlr. unit. real.</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.actual">Total real.</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.payments">Pago</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.payments">Falta pagar</th>
                            <th class="px-2 py-2.5 font-medium min-w-[13rem]" x-show="groups.control">Controle</th>
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
                                        {{-- Origem no Kanban: a linha nasceu de um card (specs/23 §6). --}}
                                        <span x-show="row.card_id" x-cloak
                                              class="shrink-0 text-[10px] px-1.5 py-0.5 rounded-full bg-brand-orange/15 text-brand-orange-deep"
                                              :title="`Criada a partir do card #${row.card_id}`"
                                              x-text="`#${row.card_id}`"></span>
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
                                    <input type="text" inputmode="decimal" x-model="row.unit_actual" @input="touch(row)" :disabled="readonly"
                                           placeholder="Não realizado"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5 text-right text-xs font-medium text-brand-ink whitespace-nowrap" x-show="groups.actual"
                                    x-text="row.unit_actual === null || row.unit_actual === '' ? '—' : brl(row.total_actual)"></td>

                                <td class="px-2 py-1.5 text-right whitespace-nowrap" x-show="groups.payments">
                                    <button type="button" @click="openPayments(row)"
                                            class="text-xs text-brand-ink hover:text-brand-orange-deep hover:underline"
                                            x-text="brl(row.paid)"></button>
                                </td>
                                <td class="px-2 py-1.5 text-right text-xs whitespace-nowrap" x-show="groups.payments"
                                    :class="row.pending > 0 ? 'text-brand-orange-deep' : (row.pending < 0 ? 'text-red-600' : 'text-steel')"
                                    x-text="row.pending < 0 ? `${brl(row.pending)} (pago a maior)` : brl(row.pending)"></td>

                                <td class="px-2 py-1.5" x-show="groups.control">
                                    <div class="flex items-center gap-1">
                                        <template x-for="chip in row.documents" :key="chip.kind">
                                            <button type="button" @click="openDocuments(row, chip.kind)" :title="chip.label"
                                                    class="relative inline-flex items-center justify-center w-6 h-6 rounded-md border text-[10px] transition-colors"
                                                    :class="chip.count > 0
                                                        ? 'border-green-300 bg-green-50 text-green-700'
                                                        : 'border-hairline text-steel hover:border-brand-orange hover:text-brand-orange-deep'">
                                                <i class="fa-solid" :class="chip.icon"></i>
                                                <span x-show="chip.count > 1" x-cloak
                                                      class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[0.9rem] h-3.5 px-1 rounded-full bg-brand-ink text-white text-[9px]"
                                                      x-text="chip.count"></span>
                                            </button>
                                        </template>
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
                            <td colspan="6"></td>
                            <td class="px-2 py-2.5" x-show="groups.estimate1"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.estimate1">{{ $money($footer->e1) }}</td>
                            <td class="px-2 py-2.5" x-show="groups.estimate2" x-cloak></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.estimate2" x-cloak>{{ $money($footer->e2) }}</td>
                            <td class="px-2 py-2.5" x-show="groups.actual"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.actual">{{ $money($footer->act) }}</td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.payments">{{ $money($paidTotal) }}</td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.payments">{{ $money($footer->act - $paidTotal) }}</td>
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
