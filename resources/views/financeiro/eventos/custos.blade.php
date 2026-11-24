@php
    $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $filtroAtivo = ! \App\Services\Finance\CostItemFilter::fromRequest(request())->isEmpty();
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Custos — {{ $evento->name }}</h2></x-slot>

    <x-page-header title="Custos" :subtitle="$evento->name" icon="fa-arrow-trend-down">
        <x-slot name="actions">
            {{-- Leva os filtros aplicados: o arquivo sai com o mesmo recorte da tela. --}}
            <a href="{{ route('finance.export', ['evento' => $evento] + request()->only(\App\Services\Finance\CostItemFilter::KEYS)) }}"
               title="{{ $filtroAtivo ? 'Exporta só as linhas filtradas' : 'Exporta a planilha completa' }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-file-excel"></i> {{ $filtroAtivo ? 'Exportar filtrado' : 'Exportar' }}
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

    @include('financeiro.eventos._cost_filters', ['clearUrl' => route('finance.costs.index', $evento)])

    <div x-data="financeCosts({
            urls: {
                rows: '{{ route('finance.costs.rows', $evento) }}',
                costBase: '{{ url('financeiro/custos') }}',
                costStore: '{{ route('finance.costs.store', $evento) }}',
                costBulk: '{{ route('finance.costs.bulk', $evento) }}',
                documentBase: '{{ url('financeiro/documentos') }}',
                paymentBase: '{{ url('financeiro/pagamentos') }}',
                boardCardBase: '{{ url('quadros') }}',
            },
            lookups: {{ Illuminate\Support\Js::from($lookups) }},
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

        <div x-ref="grid" class="bg-white border border-hairline rounded-xl overflow-hidden scroll-mt-4">
            <div class="overflow-x-auto" @scroll.passive="closePicker()">
                <table class="min-w-full text-sm border-collapse">
                    <thead class="bg-surface text-left text-steel">
                        <tr class="text-xs uppercase tracking-wide">
                            <th class="px-2 py-2.5 w-8 sticky left-0 bg-surface z-10">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll()" :disabled="loading"
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                            </th>
                            <th class="px-2 py-2.5 font-medium sticky left-8 bg-surface z-10 min-w-[10rem]">Item</th>
                            <th class="px-2 py-2.5 font-medium min-w-[16rem]">Descrição</th>
                            <th class="px-2 py-2.5 font-medium min-w-[11rem]">Centro de custo</th>
                            <th class="px-2 py-2.5 font-medium min-w-[11rem]">Status</th>
                            <th class="px-2 py-2.5 font-medium min-w-[7.5rem] text-right" x-show="groups.estimate1">Vlr. unit.</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right whitespace-nowrap" x-show="groups.estimate1">Total prev.</th>
                            <th class="px-2 py-2.5 font-medium min-w-[7.5rem] text-right" x-show="groups.estimate2" x-cloak>Vlr. unit. 2</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right" x-show="groups.estimate2" x-cloak>Total prev. 2</th>
                            <th class="px-2 py-2.5 font-medium min-w-[8.5rem] w-36 text-right whitespace-nowrap" x-show="groups.actual">Vlr. unit. real.</th>
                            <th class="px-2 py-2.5 font-medium w-32 text-right whitespace-nowrap" x-show="groups.actual">Total real.</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.payments">Pago</th>
                            <th class="px-2 py-2.5 font-medium w-28 text-right" x-show="groups.payments">Falta pagar</th>
                            <th class="px-2 py-2.5 font-medium w-36">Vencimento</th>
                            <th class="px-2 py-2.5 font-medium min-w-[12rem]">Empresa</th>
                            <th class="px-2 py-2.5 font-medium min-w-[5rem] text-right">Diárias</th>
                            <th class="px-2 py-2.5 font-medium min-w-[5rem] text-right">Quant.</th>
                            <th class="px-2 py-2.5 w-20"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-hairline">
                        {{-- Esqueleto enquanto as linhas carregam: a página (menu, filtros) já está
                             utilizável; só a grade espera. --}}
                        <template x-for="i in (loading ? 8 : 0)" :key="`sk-${i}`">
                                <tr aria-hidden="true">
                                    <td colspan="18" class="px-3 py-2.5">
                                        <div class="flex items-center gap-3 animate-pulse motion-reduce:animate-none">
                                            <div class="h-4 w-4 rounded bg-hairline"></div>
                                            <div class="h-7 w-36 rounded-md bg-hairline"></div>
                                            <div class="h-7 flex-1 max-w-sm rounded-md bg-hairline"></div>
                                            <div class="h-7 w-32 rounded-md bg-hairline"></div>
                                            <div class="h-7 w-28 rounded-md bg-hairline"></div>
                                            <div class="hidden lg:block h-7 w-24 rounded-md bg-hairline"></div>
                                            <div class="hidden lg:block h-7 w-24 rounded-md bg-hairline"></div>
                                            <div class="hidden xl:block h-7 w-40 rounded-md bg-hairline"></div>
                                        </div>
                                    </td>
                                </tr>
                        </template>

                        <tr x-show="loadError" x-cloak>
                            <td colspan="18" class="px-4 py-10 text-center">
                                <p class="text-sm text-brand-ink"><i class="fa-solid fa-triangle-exclamation text-red-600 mr-1"></i> <span x-text="loadError"></span></p>
                                <button type="button" @click="load(pagination.current_page)"
                                        class="mt-3 inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                                    <i class="fa-solid fa-rotate-right"></i> Tentar de novo
                                </button>
                            </td>
                        </tr>

                        <template x-for="row in rows" :key="row.id">
                            <tr :id="`linha-${row.id}`" x-show="!loading" class="hover:bg-surface/60 align-middle"
                                :class="savedIds.includes(row.id) && 'bg-green-50'">
                                <td class="px-2 py-1.5 sticky left-0 bg-white z-10">
                                    <input type="checkbox" :value="row.id" x-model.number="selected"
                                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                </td>

                                {{-- Item e Empresa: botão com o rótulo + seletor compartilhado (um painel
                                     só para a grade toda, ver "picker" abaixo) — cada linha deixou de
                                     carregar um <select> com todas as opções. --}}
                                <td class="px-2 py-1.5 sticky left-8 bg-white z-10">
                                    <button type="button" data-picker-anchor :disabled="readonly"
                                            @click="openPicker($el, row, 'fornecedor_categoria_id', 'categorias')"
                                            class="w-44 min-h-[34px] flex items-center justify-between gap-1 rounded-md border border-gray-300 bg-white px-2.5 text-xs text-left hover:border-brand-orange focus:outline-none focus:border-brand-orange focus:ring-1 focus:ring-brand-orange disabled:bg-gray-50 disabled:text-gray-400">
                                        <span class="truncate" :class="row.fornecedor_categoria_id ? 'text-brand-ink' : 'text-steel'"
                                              x-text="categoryLabel(row) ?? '—'"></span>
                                        <i class="fa-solid fa-chevron-down text-[9px] text-steel shrink-0"></i>
                                    </button>
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

                                {{-- Centro de custo (specs/25), logo após a descrição: item, descrição e centro
                                     respondem juntos "o que é e onde aloca". Inativo só aparece (e só é
                                     escolhível) na linha que já o usa. --}}
                                <td class="px-2 py-1.5">
                                    <select x-model="row.cost_center_id" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        <option value="">—</option>
                                        @foreach ($costCenters as $centro)
                                            @if ($centro['active'])
                                                <option value="{{ $centro['id'] }}">{{ $centro['label'] }}</option>
                                            @else
                                                <option value="{{ $centro['id'] }}"
                                                        :hidden="row.cost_center_id != {{ $centro['id'] }}"
                                                        :disabled="row.cost_center_id != {{ $centro['id'] }}">{{ $centro['label'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
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
                                    {{-- Mesma máscara de centavos do "Vlr. unit. real." (valor já chega em string BR
                                         pelo normalizeCostRow). --}}
                                    <input type="text" inputmode="decimal" :value="row.unit_estimated_1"
                                           @input="row.unit_estimated_1 = maskMoneyDigits($event.target.value); touch(row)"
                                           :disabled="readonly" placeholder="0,00"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5 text-right text-xs text-brand-ink whitespace-nowrap" x-show="groups.estimate1"
                                    x-text="brl(row.total_estimated_1)"></td>

                                <td class="px-2 py-1.5" x-show="groups.estimate2" x-cloak>
                                    <input type="text" inputmode="decimal" :value="row.unit_estimated_2"
                                           @input="row.unit_estimated_2 = maskMoneyDigits($event.target.value); touch(row)"
                                           :disabled="readonly" placeholder="—"
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
                                    {{-- Pílula com ícone deixa a afordância visível sempre, não só no hover —
                                         sem isso ninguém descobre que o valor pago abre os pagamentos. --}}
                                    <button type="button" @click="openPayments(row)" title="Ver pagamentos"
                                            class="group relative inline-flex items-center gap-1 rounded-md border border-hairline bg-surface px-1.5 py-0.5 text-xs font-medium text-brand-ink transition-colors hover:border-brand-orange hover:bg-brand-orange/10 hover:text-brand-orange-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange focus-visible:ring-offset-1">
                                        <i class="fa-solid fa-wallet text-[10px] text-steel group-hover:text-brand-orange-deep"></i>
                                        <span x-text="brl(row.paid)"></span>
                                        {{-- Balão com a quantidade de pagamentos já lançados — some quando não há nenhum. --}}
                                        <span x-show="row.payments_count > 0" x-text="row.payments_count"
                                              class="absolute -top-1.5 -right-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-brand-orange px-1 text-[10px] font-semibold leading-none text-white"></span>
                                    </button>
                                </td>
                                {{-- "pago a mais" vira tooltip nativo (title), não mais texto ao lado do número —
                                     só aparece passando o mouse, sem disputar espaço com o valor. --}}
                                <td class="px-2 py-1.5 text-right text-xs whitespace-nowrap" x-show="groups.payments"
                                    :class="row.pending > 0 ? 'text-brand-orange-deep' : (row.pending < 0 ? 'text-red-600' : 'text-steel')"
                                    :title="row.pending < 0 ? 'Pago a mais' : null"
                                    x-text="brl(row.pending)"></td>

                                {{-- Vencimento do pagamento (vem do modal do card ou é editado aqui). Vermelho
                                     quando a data já passou ou é hoje — ver isDueHighlighted em finance.js. --}}
                                <td class="px-2 py-1.5">
                                    <input type="date" x-model="row.due_date" @change="save(row)" :disabled="readonly"
                                           :title="row.due_date ? null : 'Sem vencimento'"
                                           class="w-full rounded-md text-xs focus:border-brand-orange focus:ring-brand-orange"
                                           :class="isDueHighlighted(row.due_date) ? 'border-red-400 bg-red-50 text-red-600 font-medium' : 'border-gray-300'">
                                </td>

                                <td class="px-2 py-1.5">
                                    <button type="button" data-picker-anchor :disabled="readonly"
                                            @click="openPicker($el, row, 'fornecedor_id', 'fornecedores')"
                                            :title="!row.fornecedor_id && row.supplier_name ? 'Nome vindo da planilha, sem fornecedor cadastrado' : supplierLabel(row)"
                                            class="w-60 min-h-[34px] flex items-center justify-between gap-1 rounded-md border border-gray-300 bg-white px-2.5 text-xs text-left hover:border-brand-orange focus:outline-none focus:border-brand-orange focus:ring-1 focus:ring-brand-orange disabled:bg-gray-50 disabled:text-gray-400">
                                        <span class="truncate"
                                              :class="row.fornecedor_id ? 'text-brand-ink' : (row.supplier_name ? 'text-steel italic' : 'text-steel')"
                                              x-text="supplierLabel(row) ?? '—'"></span>
                                        <i class="fa-solid fa-chevron-down text-[9px] text-steel shrink-0"></i>
                                    </button>
                                </td>

                                <td class="px-2 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.daily_count" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-2 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.quantity" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>

                                <td class="px-2 py-1.5 text-right whitespace-nowrap">
                                    {{-- "Ver" abre a ficha com TODOS os campos da linha (inclusive ART,
                                         Autorizado por, documentos e observações, que saíram da grade). --}}
                                    <button type="button" @click="openDetail(row)" title="Ver registro completo"
                                            class="text-steel hover:text-brand-orange-deep focus:outline-none focus-visible:text-brand-orange-deep">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </button>
                                    <button type="button" @click="duplicate(row)" :disabled="readonly" title="Duplicar linha"
                                            class="ml-1.5 text-steel hover:text-brand-ink disabled:opacity-30">
                                        <i class="fa-solid fa-copy text-xs"></i>
                                    </button>
                                    <button type="button" @click="remove(row)" :disabled="readonly" title="Excluir linha"
                                            class="ml-1.5 text-steel hover:text-red-600 disabled:opacity-30">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="!loading && !loadError && rows.length === 0" x-cloak>
                            <td colspan="18" class="px-4 py-10 text-center text-sm text-steel">
                                @if ($filtroAtivo)
                                    Nenhuma linha com esses filtros. <a href="{{ route('finance.costs.index', $evento) }}" class="text-brand-orange-deep hover:underline">Limpar filtros</a>
                                @else
                                    Nenhuma linha de custo. Envie um card do Kanban para o Financeiro ou adicione uma linha abaixo.
                                @endif
                            </td>
                        </tr>
                    </tbody>

                    {{-- Rodapé: totais do FILTRO aplicado, calculados pelo servidor e ajustados a cada
                         edição pela diferença da linha (ver syncRow em finance.js). --}}
                    <tfoot class="bg-surface text-xs font-semibold text-brand-ink">
                        <tr>
                            <td class="px-2 py-2.5 sticky left-0 bg-surface z-10"></td>
                            <td class="px-2 py-2.5 sticky left-8 bg-surface z-10">TOTAL</td>
                            <td class="px-2 py-2.5 text-steel font-normal">
                                <span x-show="!loading" x-text="`${footer.total} linha(s)`"></span>
                                <span x-show="loading" class="inline-block h-3 w-16 rounded bg-hairline animate-pulse motion-reduce:animate-none"></span>
                            </td>
                            <td></td>
                            <td></td>
                            <td class="px-2 py-2.5" x-show="groups.estimate1"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.estimate1" x-text="loading ? '…' : brl(footer.estimated1)"></td>
                            <td class="px-2 py-2.5" x-show="groups.estimate2" x-cloak></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.estimate2" x-cloak x-text="loading ? '…' : brl(footer.estimated2)"></td>
                            <td class="px-2 py-2.5" x-show="groups.actual"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.actual" x-text="loading ? '…' : brl(footer.actual)"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.payments" x-text="loading ? '…' : brl(footer.paid)"></td>
                            <td class="px-2 py-2.5 text-right whitespace-nowrap" x-show="groups.payments" x-text="loading ? '…' : brl(footer.pending)"></td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-hairline p-3">
                @unless ($sheet->isClosed())
                    <button type="button" @click="addRow()" :disabled="loading"
                            class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep disabled:opacity-50">
                        <i class="fa-solid fa-plus"></i> Adicionar linha
                    </button>
                @else
                    <span></span>
                @endunless

                {{-- Paginação no cliente: troca de página busca só as linhas, sem recarregar a tela. --}}
                <nav x-show="pagination.last_page > 1" x-cloak class="flex items-center gap-2 text-xs text-steel" aria-label="Paginação da grade">
                    <span x-text="`${pagination.from ?? 0}–${pagination.to ?? 0} de ${pagination.total}`"></span>
                    <button type="button" @click="goToPage(pagination.current_page - 1)" :disabled="loading || pagination.current_page <= 1"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-hairline text-brand-ink hover:bg-surface disabled:opacity-40" title="Página anterior">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    <span class="text-brand-ink" x-text="`Página ${pagination.current_page} de ${pagination.last_page}`"></span>
                    <button type="button" @click="goToPage(pagination.current_page + 1)" :disabled="loading || pagination.current_page >= pagination.last_page"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-hairline text-brand-ink hover:bg-surface disabled:opacity-40" title="Próxima página">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </nav>
            </div>
        </div>

        {{-- Seletor compartilhado de Item/Empresa: um painel só, posicionado (fixed) junto da célula
             clicada. Busca por texto e teclado (setas, Enter, Esc). --}}
        <div x-show="picker.open" x-cloak
             @click.outside="if (!$event.target.closest('[data-picker-anchor]')) closePicker()"
             @keydown.escape.stop="closePicker()"
             @scroll.window.passive="closePicker()" @resize.window="closePicker()"
             :style="`top:${picker.top}px;left:${picker.left}px;width:${picker.width}px`"
             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="fixed z-40 rounded-xl border border-hairline bg-white p-2 shadow-lg" role="dialog"
             :aria-label="picker.list === 'categorias' ? 'Escolher item' : 'Escolher empresa'">
            <div class="relative mb-2">
                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                <input type="text" x-ref="pickerSearch" x-model="picker.search" @input="picker.active = 0"
                       @keydown.arrow-down.prevent="movePicker(1)" @keydown.arrow-up.prevent="movePicker(-1)"
                       @keydown.enter.prevent="choosePickerActive()"
                       :placeholder="picker.list === 'categorias' ? 'Pesquisar item' : 'Pesquisar empresa'"
                       class="w-full pl-7 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
            </div>
            <ul x-ref="pickerList" class="max-h-60 overflow-y-auto space-y-0.5" role="listbox">
                <li>
                    <button type="button" data-index="0" @click="choosePicker(null)" @mouseenter="picker.active = 0"
                            class="w-full rounded-md px-2 py-1.5 text-left text-sm text-steel"
                            :class="picker.active === 0 && 'bg-surface'"
                            x-text="picker.list === 'categorias' ? 'Sem item' : 'Sem empresa'"></button>
                </li>
                <template x-for="(option, index) in pickerOptions" :key="option.id">
                    <li>
                        <button type="button" :data-index="index + 1" @click="choosePicker(option)" @mouseenter="picker.active = index + 1"
                                class="w-full flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-brand-ink"
                                :class="picker.active === index + 1 && 'bg-surface'">
                            <span class="flex-1 truncate" x-text="option.label"></span>
                            <i x-show="picker.row && String(picker.row[picker.field]) === String(option.id)" class="fa-solid fa-check text-[11px] text-brand-orange-deep"></i>
                        </button>
                    </li>
                </template>
                <li x-show="pickerOptions.length === 0" class="px-2 py-1.5 text-xs text-steel">Nada encontrado.</li>
            </ul>
        </div>

        {{-- Datalists de descrição por categoria (autocomplete dos 168 itens do modelo) --}}
        @foreach ($presets as $categoriaId => $descriptions)
            <datalist id="presets-{{ $categoriaId }}">
                @foreach ($descriptions as $description)
                    <option value="{{ $description }}"></option>
                @endforeach
            </datalist>
        @endforeach

        @include('financeiro.eventos._cost_detail')
        @include('financeiro.eventos._drawer', ['sheet' => $sheet, 'sources' => $sources, 'bankAccounts' => $bankAccounts])
    </div>
</x-app-layout>
