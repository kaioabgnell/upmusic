@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Receitas — {{ $evento->name }}</h2></x-slot>

    <x-page-header title="Receitas" :subtitle="$evento->name" icon="fa-arrow-trend-up">
        <x-slot name="actions">
            <a href="{{ route('finance.show', $evento) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-chart-pie"></i> Resumo
            </a>
        </x-slot>
    </x-page-header>

    @include('financeiro.eventos._tabs', ['active' => 'receitas'])

    <div x-data="financeRevenues({
            urls: {
                revenueBase: '{{ url('financeiro/receitas') }}',
                revenueStore: '{{ route('finance.revenues.store', $evento) }}',
            },
            rows: {{ Illuminate\Support\Js::from($revenues->map(fn ($r) => App\Support\FinancePresenter::revenue($r))->values()) }},
            readonly: {{ Illuminate\Support\Js::from($sheet->isClosed()) }},
         })">

        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface text-left text-steel text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2.5 font-medium min-w-[12rem]">Receita</th>
                            <th class="px-3 py-2.5 font-medium min-w-[14rem]">Descrição</th>
                            <th class="px-3 py-2.5 font-medium w-32 text-right">Valor previsto</th>
                            <th class="px-3 py-2.5 font-medium w-32 text-right">Valor realizado</th>
                            <th class="px-3 py-2.5 font-medium w-32 text-right">Recebido</th>
                            <th class="px-3 py-2.5 font-medium w-32 text-right">Falta receber</th>
                            <th class="px-3 py-2.5 font-medium min-w-[10rem]">Recebido por</th>
                            <th class="px-3 py-2.5 font-medium min-w-[12rem]">Obs</th>
                            <th class="px-3 py-2.5 w-10"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-hairline">
                        <template x-for="row in rows" :key="row.id">
                            <tr :id="`receita-${row.id}`" class="hover:bg-surface/60">
                                <td class="px-3 py-1.5">
                                    <select x-model="row.category" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        @foreach ($categories as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-1.5">
                                    {{-- "vai com a descrição do que entrou de patrocínio" --}}
                                    <input type="text" data-field="description" x-model="row.description" @input="touch(row)"
                                           :disabled="readonly" placeholder="Patrocinador, cota, lote…" maxlength="180"
                                           class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.estimated_value" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.actual_value" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" inputmode="decimal" x-model="row.received_value" @input="touch(row)" :disabled="readonly"
                                           class="w-full text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                {{-- Coluna gerada no banco: somente leitura, sempre igual a realizado - recebido. --}}
                                <td class="px-3 py-1.5 text-right text-xs whitespace-nowrap"
                                    :class="row.pending_value > 0 ? 'text-brand-orange-deep' : 'text-steel'"
                                    x-text="brl(row.pending_value)"></td>
                                <td class="px-3 py-1.5">
                                    <select x-model="row.finance_payment_source_id" @change="save(row)" :disabled="readonly"
                                            class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                        <option value="">—</option>
                                        @foreach ($sources as $source)
                                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="text" x-model="row.notes" @input="touch(row)" :disabled="readonly" maxlength="255"
                                           class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-xs">
                                </td>
                                <td class="px-3 py-1.5 text-right">
                                    <button type="button" @click="remove(row)" :disabled="readonly"
                                            class="text-steel hover:text-red-600 disabled:opacity-30">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="rows.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-steel">Nenhuma linha de receita.</td>
                        </tr>
                    </tbody>

                    <tfoot class="bg-surface text-xs font-semibold text-brand-ink">
                        <tr>
                            <td class="px-3 py-2.5" colspan="2">TOTAL GERAL</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $money($totals['estimated']) }}</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $money($totals['actual']) }}</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $money($totals['received']) }}</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $money($totals['pending']) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @unless ($sheet->isClosed())
                <div class="p-3 border-t border-hairline flex flex-wrap items-center gap-2">
                    <button type="button" @click="addRow('patrocinio')"
                            class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Adicionar patrocínio
                    </button>
                    <button type="button" @click="addRow('outros')"
                            class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Outra receita
                    </button>
                    <span class="text-xs text-steel">
                        Os totais do rodapé são recalculados ao recarregar; a linha salva sozinha ao sair do campo.
                    </span>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
