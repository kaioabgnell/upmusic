@php
    $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $maxCategoria = collect($byCategory)->flatMap(fn ($c) => [$c['estimated'], $c['actual']])->max() ?: 1;
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Financeiro — {{ $evento->name }}</h2></x-slot>

    <x-page-header :title="$evento->name" icon="fa-file-invoice-dollar"
        :subtitle="collect([
            $evento->start_date?->format('d/m/Y').' — '.$evento->end_date?->format('d/m/Y'),
            $evento->location,
        ])->filter()->implode(' · ')">
        <x-slot name="actions">
            <a href="{{ route('finance.export', $evento) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-file-excel"></i> Exportar XLSX
            </a>
            <a href="{{ route('finance.index') }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </x-slot>
    </x-page-header>

    @include('financeiro.eventos._tabs', ['active' => 'resumo'])

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Alertas de consistência: o que a planilha nunca avisou (specs/23 §8.2). --}}
    @if ($alerts)
        <div class="mb-6 rounded-xl border border-brand-orange/40 bg-brand-orange/5 p-4">
            <p class="text-sm font-semibold text-brand-ink mb-2">
                <i class="fa-solid fa-triangle-exclamation text-brand-orange-deep mr-1"></i> Pontos de atenção
            </p>
            <ul class="space-y-1.5">
                @foreach ($alerts as $alert)
                    <li class="text-sm text-brand-ink flex items-start gap-2">
                        <i class="fa-solid {{ $alert['icon'] }} text-brand-orange-deep mt-1 text-xs"></i>
                        <span>
                            {{ $alert['text'] }}
                            @if ($alert['filter'])
                                <a href="{{ route('finance.costs.index', $evento) }}?{{ http_build_query($alert['filter']) }}"
                                   class="text-brand-orange-deep hover:underline">ver linhas</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- RESUMO GERAL PREVISTO x REALIZADO --}}
    <div class="grid gap-4 md:grid-cols-2 mb-6">
        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="px-4 py-3 bg-brand-ink text-white text-sm font-semibold">RESUMO GERAL PREVISTO</div>
            <dl class="divide-y divide-hairline">
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-sm text-steel">Receita</dt>
                    <dd class="font-medium text-brand-ink">{{ $money($summary['revenue']['estimated']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-sm text-steel">
                        Custo
                        @if ($sheet->uses_second_estimate)
                            <span class="block text-[11px]">Previsto 2 quando preenchido, senão Previsto 1</span>
                        @endif
                    </dt>
                    <dd class="font-medium text-brand-ink">{{ $money($summary['cost']['current_estimate']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3 bg-surface">
                    <dt class="text-sm font-semibold text-brand-ink">Resultado</dt>
                    <dd class="text-lg font-semibold {{ $summary['result']['estimated'] < 0 ? 'text-red-600' : 'text-brand-ink' }}">
                        {{ $money($summary['result']['estimated']) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="px-4 py-3 bg-brand-orange text-brand-ink text-sm font-semibold">RESUMO GERAL REALIZADO</div>
            <dl class="divide-y divide-hairline">
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-sm text-steel">Receita</dt>
                    <dd class="font-medium text-brand-ink">{{ $money($summary['revenue']['actual']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-sm text-steel">Custo</dt>
                    <dd class="font-medium text-brand-ink">{{ $money($summary['cost']['actual']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3 bg-surface">
                    <dt class="text-sm font-semibold text-brand-ink">Resultado</dt>
                    <dd class="text-lg font-semibold {{ $summary['result']['actual'] < 0 ? 'text-red-600' : 'text-brand-ink' }}">
                        {{ $money($summary['result']['actual']) }}
                    </dd>
                </div>
            </dl>
            <div class="px-4 py-2.5 border-t border-hairline text-xs text-steel">
                Desvio do custo: <span class="font-medium text-brand-ink">{{ $money($summary['deviation']['deviation']) }}</span>
                · Realização: <span class="font-medium text-brand-ink">{{ $pct($summary['deviation']['pct']) }}</span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3 mb-6">
        {{-- CUSTO POR ITEM --}}
        <div class="lg:col-span-2 bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-chart-simple text-brand-orange mr-2"></i>Custo por item</h3>
                <span class="text-xs text-steel">Previsto vs. realizado</span>
            </div>
            @if (empty($byCategory))
                <x-empty-state icon="fa-table-list" title="Sem linhas de custo"
                    message="Envie um card do Kanban para o financeiro ou adicione linhas na aba Custos." />
            @else
                <div class="p-4 space-y-3">
                    @foreach ($byCategory as $line)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-brand-ink">{{ $line['label'] }}</span>
                                <span class="text-steel">
                                    {{ $money($line['estimated']) }} <span class="text-hairline">/</span>
                                    <span class="text-brand-ink font-medium">{{ $money($line['actual']) }}</span>
                                </span>
                            </div>
                            <div class="mt-1.5 space-y-1">
                                <div class="h-2 rounded-full bg-surface overflow-hidden">
                                    <div class="h-full bg-brand-ink/70" style="width: {{ max(1, $line['estimated'] / $maxCategoria * 100) }}%"></div>
                                </div>
                                <div class="h-2 rounded-full bg-surface overflow-hidden">
                                    <div class="h-full bg-brand-orange" style="width: {{ max(1, $line['actual'] / $maxCategoria * 100) }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-4">
            {{-- ANDAMENTO --}}
            <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-hairline">
                    <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-money-bill-transfer text-brand-orange mr-2"></i>Andamento</h3>
                </div>
                <div class="p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-steel">Pago</span>
                        <span class="font-medium text-brand-ink">{{ $money($summary['progress']['paid']) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-1">
                        <span class="text-steel">Falta pagar</span>
                        <span class="font-medium {{ $summary['progress']['pending'] > 0 ? 'text-brand-orange-deep' : 'text-brand-ink' }}">
                            {{ $money($summary['progress']['pending']) }}
                        </span>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-surface overflow-hidden">
                        <div class="h-full bg-brand-orange" style="width: {{ min(100, $summary['progress']['pct'] ?? 0) }}%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-steel">{{ $pct($summary['progress']['pct']) }} do realizado</p>

                    @if ($bySource)
                        <ul class="mt-4 space-y-1.5 border-t border-hairline pt-3">
                            @foreach ($bySource as $source)
                                <li class="flex items-center justify-between text-xs">
                                    <span class="text-steel">{{ $source['label'] }}</span>
                                    <span class="text-brand-ink">{{ $money($source['total']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- CONTROLE DOCUMENTAL --}}
            <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-hairline">
                    <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-folder-open text-brand-orange mr-2"></i>Controle documental</h3>
                </div>
                <ul class="p-4 grid grid-cols-2 gap-2">
                    @foreach ($documentKinds as $kind)
                        <li class="flex items-center gap-2 text-xs">
                            <i class="fa-solid {{ $kind->icon() }} {{ ($documentCounts[$kind->value] ?? 0) > 0 ? 'text-green-600' : 'text-steel' }}"></i>
                            <span class="text-steel">{{ $kind->label() }}</span>
                            <span class="ml-auto font-medium text-brand-ink">{{ $documentCounts[$kind->value] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="px-4 pb-4 text-[11px] text-steel">
                    Os arquivos vêm dos anexos dos cards — nada é enviado duas vezes.
                </p>
            </div>
        </div>
    </div>

    {{-- ACERTO SÓCIOS + configuração da planilha --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="bg-white border border-hairline rounded-xl overflow-hidden"
             x-data="financeSettlements({
                partners: {{ Illuminate\Support\Js::from($settlements) }},
                result: {{ $summary['result']['actual'] }},
             })">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-handshake text-brand-orange mr-2"></i>Acerto de sócios</h3>
                <span class="text-xs" :class="totalPct === 100 ? 'text-steel' : 'text-brand-orange-deep'"
                      x-text="`${totalPct.toLocaleString('pt-BR')}% distribuído`"></span>
            </div>

            <form method="POST" action="{{ route('finance.settlements.sync', $evento) }}" class="p-4">
                @csrf @method('PUT')
                <table class="w-full text-sm">
                    <thead class="text-left text-steel text-xs">
                        <tr>
                            <th class="pb-2 font-medium">Repasse sócios</th>
                            <th class="pb-2 font-medium w-28">Porcentagem</th>
                            <th class="pb-2 font-medium w-36 text-right">Total</th>
                            <th class="pb-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(p, i) in partners" :key="i">
                            <tr>
                                <td class="py-1.5 pr-2">
                                    <input type="hidden" :name="`partners[${i}][id]`" :value="p.id ?? ''">
                                    <input type="text" :name="`partners[${i}][partner_name]`" x-model="p.partner_name" required
                                           class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                </td>
                                <td class="py-1.5 pr-2">
                                    <input type="number" step="0.01" min="0" max="100" :name="`partners[${i}][percentage]`"
                                           x-model.number="p.percentage"
                                           class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                </td>
                                <td class="py-1.5 text-right text-brand-ink" x-text="brl(amountOf(p))"></td>
                                <td class="py-1.5 text-right">
                                    <button type="button" @click="partners.splice(i, 1)" class="text-steel hover:text-red-600">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="partners.length === 0">
                            <td colspan="4" class="py-3 text-center text-xs text-steel">Nenhum sócio cadastrado no acerto.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-3 flex items-center justify-between">
                    <button type="button" @click="partners.push({ partner_name: '', percentage: 0, manual_amount: false })"
                            class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-3 py-1.5 text-xs font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Adicionar sócio
                    </button>
                    @unless ($sheet->isClosed())
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar acerto
                        </button>
                    @endunless
                </div>
            </form>
        </div>

        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-sliders text-brand-orange mr-2"></i>Configuração da planilha</h3>
            </div>
            <form method="POST" action="{{ route('finance.config.update', $evento) }}" class="p-4 space-y-4">
                @csrf @method('PUT')
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="uses_second_estimate" value="1" @checked($sheet->uses_second_estimate)
                           class="mt-1 rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                    <span>
                        <span class="block text-sm font-medium text-brand-ink">Usar "Previsto 2"</span>
                        <span class="block text-xs text-steel">
                            Segundo cenário de previsão, para quando o valor é refinado depois da coleta de
                            orçamentos. A linha sem Previsto 2 continua valendo pelo Previsto 1.
                        </span>
                    </span>
                </label>

                <div>
                    <label class="text-xs text-steel">Observações da planilha</label>
                    <textarea name="notes" rows="3"
                              class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">{{ $sheet->notes }}</textarea>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar
                    </button>
                </div>
            </form>

            <div class="px-4 pb-4 border-t border-hairline pt-4 space-y-3">
                @if ($sheet->isClosed())
                    <p class="text-xs text-steel">
                        Fechada em {{ $sheet->closed_at?->format('d/m/Y H:i') }}. A planilha está somente leitura
                        e os anexos vinculados não podem ser excluídos no card.
                    </p>
                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('finance.reopen', $evento) }}"
                              data-confirm="Reabrir a prestação de contas deste evento?" data-confirm-button="Reabrir">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                                <i class="fa-solid fa-lock-open"></i> Reabrir prestação de contas
                            </button>
                        </form>
                    @endif
                @else
                    <p class="text-xs text-steel">
                        Fechar congela a prestação de contas: a planilha fica somente leitura e os arquivos
                        que provam as despesas não podem mais ser excluídos pelo Kanban.
                    </p>
                    <form method="POST" action="{{ route('finance.close', $evento) }}"
                          data-confirm="Falta pagar {{ $money($summary['progress']['pending']) }}. Fechar mesmo assim?"
                          data-confirm-title="Fechar prestação de contas" data-confirm-button="Fechar">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                            <i class="fa-solid fa-lock"></i> Fechar prestação de contas
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('finance.import.preview', $evento) }}" enctype="multipart/form-data"
                      class="flex flex-wrap items-end gap-2 pt-2 border-t border-hairline">
                    @csrf
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs text-steel">Importar planilha preenchida (.xlsx)</label>
                        <input type="file" name="file" accept=".xlsx,.xls" required
                               class="mt-1 w-full text-xs text-steel file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-brand-ink">
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-xs font-medium text-brand-ink hover:bg-surface">
                        <i class="fa-solid fa-file-import"></i> Pré-visualizar
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
