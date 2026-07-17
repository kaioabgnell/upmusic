@php
    $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $chartSeries = $history->reverse()->values(); // cronológico para o gráfico

    // ---- Comparação entre fornecedores (paleta categórica validada — ver specs/02/dataviz) ----
    $comparePalette = ['#2a78d6', '#008300', '#e87ba4', '#eda100', '#1baf7a', '#eb6834', '#4a3aa7', '#e34948'];
    // Cor fixa por fornecedor (ordem alfabética dentro da categoria) — a mesma cor sempre representa
    // o mesmo fornecedor, independente de quais outros estão selecionados na comparação.
    $fornecedorColorMap = $fornecedores->values()->mapWithKeys(fn ($f, $i) => [$f->id => $comparePalette[$i % count($comparePalette)]]);

    $compareAllPoints = $compareData->flatMap(fn ($s) => collect($s['points']));
    // Eixo do gráfico limitado ao período que realmente tem registro — o filtro de período (3m/6m/1y/all)
    // só decide quais registros entram na comparação, não o quanto o eixo deve se estender vazio.
    $compareChartStart = $compareAllPoints->isNotEmpty() ? \Carbon\Carbon::parse($compareAllPoints->min('date')) : null;
    $compareChartEnd = $compareAllPoints->isNotEmpty() ? \Carbon\Carbon::parse($compareAllPoints->max('date')) : null;

    $compareRows = $compareData
        ->flatMap(fn ($s) => collect($s['points'])->map(fn ($p) => [
            'date' => $p['date'],
            'date_br' => $p['date_br'],
            'price' => $p['price'],
            'fornecedor' => $s['fornecedor'],
            'color' => $fornecedorColorMap[$s['fornecedor_id']] ?? '#898781',
        ]))
        ->sortByDesc('date')
        ->values();

    // ---- Dados para o Chart.js (eixo X = data real, eixo Y = preço; tooltip mostra os dados no hover) ----
    $evolucaoPoints = $chartSeries->map(fn ($p) => ['x' => $p['reference_date'], 'y' => $p['price']])->values();
    $compareDatasets = $compareData->map(fn ($series) => [
        'label' => $series['fornecedor'],
        'borderColor' => $fornecedorColorMap[$series['fornecedor_id']] ?? '#898781',
        'backgroundColor' => $fornecedorColorMap[$series['fornecedor_id']] ?? '#898781',
        'data' => collect($series['points'])->map(fn ($p) => ['x' => $p['date'], 'y' => $p['price']])->values(),
    ])->values();

    // ---- Linha de referência "Preço Interno" (percorre todo o gráfico, se cadastrado na categoria) ----
    $precoInterno = $selectedCategoria?->preco_interno;
    $evolucaoPrecoInternoLine = ($precoInterno !== null && $chartSeries->isNotEmpty()) ? [
        ['x' => $chartSeries->first()['reference_date'], 'y' => (float) $precoInterno],
        ['x' => $chartSeries->last()['reference_date'], 'y' => (float) $precoInterno],
    ] : null;
    $compararPrecoInternoLine = ($precoInterno !== null && $compareChartStart && $compareChartEnd) ? [
        ['x' => $compareChartStart->format('Y-m-d'), 'y' => (float) $precoInterno],
        ['x' => $compareChartEnd->format('Y-m-d'), 'y' => (float) $precoInterno],
    ] : null;
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Evolução de preços</h2></x-slot>

    <x-page-header title="Evolução de preços" subtitle="Histórico de preços por categoria de fornecedor, em todos os eventos." icon="fa-chart-line">
        <x-slot name="actions">
            <a href="{{ route('prices.categorias.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-arrow-left"></i> Banco de preços</a>
        </x-slot>
    </x-page-header>

    {{-- "view" fica no escopo do Alpine que envolve tudo abaixo para sobreviver a qualquer submit GET
         (troca de categoria/fornecedor ou o botão "Comparar") sem voltar para a aba Evolução. --}}
    <div x-data="{ view: '{{ request('view', 'evolucao') }}' }">
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 bg-white border border-hairline rounded-xl p-4">
            <div>
                <label class="text-xs text-steel">Categoria</label>
                <select name="categoria_id" onchange="this.form.submit()" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    <option value="">— Selecione —</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->id }}" @selected($selectedCategoria?->id === $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            @if ($selectedCategoria && $fornecedores->isNotEmpty())
                <div>
                    <label class="text-xs text-steel">Fornecedor</label>
                    <select name="fornecedor_id" onchange="this.form.submit()" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                        @foreach ($fornecedores as $f)
                            <option value="{{ $f->id }}" @selected($selectedFornecedorId == $f->id)>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            {{-- Preserva a comparação e a aba ativa ao trocar os filtros acima. --}}
            @foreach ($compareIds as $id)
                <input type="hidden" name="compare_ids[]" value="{{ $id }}">
            @endforeach
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="hidden" name="view" :value="view">
        </form>

        @if (! $selectedCategoria)
            <div class="bg-white border border-hairline rounded-xl">
                <x-empty-state icon="fa-chart-line" title="Selecione uma categoria" message="Escolha uma categoria acima para ver a evolução de preços." />
            </div>
        @else
            <div>
                {{-- Evolução / Comparação --}}
            <div class="inline-flex items-center rounded-md border border-hairline p-0.5 shrink-0 mb-6">
                <button type="button" @click="view = 'evolucao'; $nextTick(() => window.upmusicResizeEvolucaoCharts?.())" class="inline-flex items-center gap-1.5 rounded px-3 h-8 text-sm font-medium transition-colors" :class="view === 'evolucao' ? 'bg-brand-orange text-brand-ink' : 'text-steel hover:text-brand-ink'">
                    <i class="fa-solid fa-chart-line"></i> Evolução
                </button>
                <button type="button" @click="view = 'comparacao'; $nextTick(() => window.upmusicResizeEvolucaoCharts?.())" class="inline-flex items-center gap-1.5 rounded px-3 h-8 text-sm font-medium transition-colors" :class="view === 'comparacao' ? 'bg-brand-orange text-brand-ink' : 'text-steel hover:text-brand-ink'">
                    <i class="fa-solid fa-code-compare"></i> Comparação
                </button>
            </div>

            {{-- Aba: Evolução --}}
            <div x-show="view === 'evolucao'" x-cloak>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white border border-hairline rounded-xl p-6">
                        <h3 class="font-semibold text-brand-ink mb-4">
                            Evolução — {{ $selectedCategoria->nome }}
                            @if ($selectedFornecedorId)
                                <span class="text-steel font-normal">/ {{ $fornecedores->firstWhere('id', $selectedFornecedorId)?->name }}</span>
                            @endif
                            @if ($selectedCategoria->unidade)
                                <span class="text-steel font-normal">· {{ $selectedCategoria->unidade->label() }}</span>
                            @endif
                        </h3>

                        @if ($fornecedores->isEmpty())
                            <p class="text-sm text-steel">Nenhum fornecedor cadastrado nesta categoria.</p>
                        @elseif ($chartSeries->isEmpty())
                            <p class="text-sm text-steel">Nenhum registro de preço para este fornecedor.</p>
                        @else
                            <div class="mb-6" style="height: 260px">
                                <canvas id="evolucao-chart"></canvas>
                            </div>

                            {{-- Tabela com variação --}}
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="text-left text-steel border-b border-hairline">
                                        <tr>
                                            <th class="py-2 font-medium">Data</th>
                                            <th class="py-2 font-medium text-right">Preço</th>
                                            <th class="py-2 font-medium text-right">Variação</th>
                                            <th class="py-2 font-medium">Fornecedor</th>
                                            <th class="py-2 font-medium">Evento</th>
                                            <th class="py-2 font-medium">Origem</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline">
                                        @foreach ($history as $row)
                                            <tr>
                                                <td class="py-2 text-brand-ink">{{ $row['reference_date_br'] }}</td>
                                                <td class="py-2 text-right font-medium text-brand-ink">{{ $money($row['price']) }}</td>
                                                <td class="py-2 text-right">
                                                    @if ($row['variation'] === null)
                                                        <span class="text-steel">—</span>
                                                    @else
                                                        <span class="{{ $row['variation'] < 0 ? 'text-green-600' : ($row['variation'] > 0 ? 'text-red-600' : 'text-steel') }}">
                                                            {{ $row['variation'] >= 0 ? '+' : '' }}{{ $money($row['variation']) }}
                                                            ({{ $row['variation_pct'] !== null ? ($row['variation_pct'] >= 0 ? '+' : '').$row['variation_pct'].'%' : '—' }})
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="py-2 text-steel">{{ $row['fornecedor'] ?? '—' }}</td>
                                                <td class="py-2 text-steel">{{ $row['event'] ?? '—' }}</td>
                                                <td class="py-2 text-steel">{{ $row['card'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Último preço por fornecedor --}}
                    <div class="bg-white border border-hairline rounded-xl p-6">
                        <h3 class="font-semibold text-brand-ink mb-4">Último preço por fornecedor</h3>
                        @if ($comparison->isEmpty())
                            <p class="text-sm text-steel">Sem registros por fornecedor ainda.</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($comparison as $row)
                                    <div class="flex items-center justify-between text-sm border-b border-hairline pb-2">
                                        <div>
                                            <p class="text-brand-ink font-medium">{{ $row['fornecedor'] }}</p>
                                            <p class="text-xs text-steel">{{ $row['reference_date'] }}</p>
                                        </div>
                                        <span class="font-semibold text-brand-ink">{{ $money($row['price']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Aba: Comparação --}}
            <div x-show="view === 'comparacao'" x-cloak>
                <div class="bg-white border border-hairline rounded-xl p-6">
                    <h3 class="font-semibold text-brand-ink mb-1"><i class="fa-solid fa-code-compare text-brand-orange mr-2"></i>Comparar fornecedores</h3>
                    <p class="text-xs text-steel mb-4">Compare o preço de 2 ou mais fornecedores da categoria "{{ $selectedCategoria->nome }}" ao longo do tempo.</p>

                    @if ($fornecedores->count() < 2)
                        <p class="text-sm text-steel">Esta categoria tem menos de 2 fornecedores — não há o que comparar.</p>
                    @else
                        <form method="GET" class="mb-6">
                            <input type="hidden" name="categoria_id" value="{{ $selectedCategoria->id }}">
                            @if ($selectedFornecedorId)
                                <input type="hidden" name="fornecedor_id" value="{{ $selectedFornecedorId }}">
                            @endif
                            {{-- Ao comparar, o submit deve manter a aba em "Comparação" (não voltar para "Evolução"). --}}
                            <input type="hidden" name="view" value="comparacao">

                            <div class="flex flex-wrap gap-x-5 gap-y-2 mb-4">
                                @foreach ($fornecedores as $f)
                                    <label class="inline-flex items-center gap-2 text-sm text-brand-ink cursor-pointer">
                                        <input type="checkbox" name="compare_ids[]" value="{{ $f->id }}" @checked(in_array($f->id, $compareIds)) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $fornecedorColorMap[$f->id] }}"></span>
                                        {{ $f->name }}
                                    </label>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="text-xs text-steel">Período</label>
                                    <select name="period" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                        <option value="3m" @selected($period === '3m')>Últimos 3 meses</option>
                                        <option value="6m" @selected($period === '6m')>Últimos 6 meses</option>
                                        <option value="1y" @selected($period === '1y')>Último 1 ano</option>
                                        <option value="all" @selected($period === 'all')>Todo o período</option>
                                    </select>
                                </div>
                                <button type="submit" class="h-9 inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                                    <i class="fa-solid fa-code-compare"></i> Comparar
                                </button>
                                <p class="text-xs text-steel">Até {{ count($comparePalette) }} fornecedores por vez.</p>
                            </div>
                        </form>

                        @if (count($compareIds) < 2)
                            <p class="text-sm text-steel">Selecione 2 ou mais fornecedores acima e clique em "Comparar".</p>
                        @elseif ($compareAllPoints->isEmpty())
                            <p class="text-sm text-steel">Nenhum registro de preço para os fornecedores selecionados neste período.</p>
                        @else
                            {{-- Eixo X = data, eixo Y = preço; o hover mostra fornecedor + data + preço de todas as
                                 séries naquele ponto (Chart.js cuida do posicionamento real por data). --}}
                            <div class="mb-4" style="height: 300px">
                                <canvas id="comparar-chart"></canvas>
                            </div>

                            {{-- Tabela: mesmos valores do gráfico, sem precisar de hover --}}
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="text-left text-steel border-b border-hairline">
                                        <tr>
                                            <th class="py-2 font-medium">Data</th>
                                            <th class="py-2 font-medium">Fornecedor</th>
                                            <th class="py-2 font-medium text-right">Preço</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline">
                                        @foreach ($compareRows as $row)
                                            <tr>
                                                <td class="py-2 text-brand-ink">{{ $row['date_br'] }}</td>
                                                <td class="py-2 text-steel">
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full shrink-0" style="background: {{ $row['color'] }}"></span>
                                                        {{ $row['fornecedor'] }}
                                                    </span>
                                                </td>
                                                <td class="py-2 text-right font-medium text-brand-ink">{{ $money($row['price']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif
    </div>

    @push('scripts')
    <script>
        // app.js é carregado como <script type="module"> (Vite) e por isso é sempre adiado — executa
        // depois deste script inline se não esperarmos o DOM terminar de carregar. Sem isso, window.Chart
        // ainda não existe quando este bloco roda, e "new window.Chart(...)" falha com
        // "window.Chart is not a constructor".
        document.addEventListener('DOMContentLoaded', function () {
            const moneyFmt = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const dateFormats = { day: 'dd/MM', week: 'dd/MM', month: 'MMM/yy', quarter: 'MMM/yy', year: 'yyyy' };

            let evolucaoChart = null;
            let compararChart = null;

            const evolucaoCanvas = document.getElementById('evolucao-chart');
            if (evolucaoCanvas) {
                const evolucaoDatasets = [{
                    label: 'Preço',
                    data: @json($evolucaoPoints),
                    borderColor: '#ff8c1e',
                    backgroundColor: '#ff8c1e',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#000000',
                    pointBorderColor: '#fcfcfb',
                    pointBorderWidth: 2,
                    tension: 0,
                }];
                @if ($evolucaoPrecoInternoLine)
                evolucaoDatasets.push({
                    label: 'Preço Interno',
                    data: @json($evolucaoPrecoInternoLine),
                    borderColor: '#000000',
                    backgroundColor: '#000000',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0,
                });
                @endif

                evolucaoChart = new window.Chart(evolucaoCanvas, {
                    type: 'line',
                    data: { datasets: evolucaoDatasets },
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy', displayFormats: dateFormats },
                                grid: { display: false },
                                ticks: { color: '#898781' },
                            },
                            y: {
                                grid: { color: '#e1e0d9' },
                                ticks: { color: '#898781', callback: moneyFmt },
                            },
                        },
                        plugins: {
                            legend: { display: {{ $evolucaoPrecoInternoLine ? 'true' : 'false' }}, labels: { color: '#52514e', usePointStyle: true, pointStyle: 'line' } },
                            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${moneyFmt(ctx.parsed.y)}` } },
                        },
                    },
                });
            }

            const compararCanvas = document.getElementById('comparar-chart');
            if (compararCanvas) {
                const datasets = @json($compareDatasets).map((d) => ({
                    ...d,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBorderColor: '#fcfcfb',
                    pointBorderWidth: 2,
                    tension: 0,
                }));
                @if ($compararPrecoInternoLine)
                datasets.push({
                    label: 'Preço Interno',
                    data: @json($compararPrecoInternoLine),
                    borderColor: '#000000',
                    backgroundColor: '#000000',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0,
                });
                @endif

                compararChart = new window.Chart(compararCanvas, {
                    type: 'line',
                    data: { datasets },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: {
                                type: 'time',
                                min: '{{ $compareChartStart?->format('Y-m-d') }}',
                                max: '{{ $compareChartEnd?->format('Y-m-d') }}',
                                time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy', displayFormats: dateFormats },
                                grid: { display: false },
                                ticks: { color: '#898781' },
                            },
                            y: {
                                grid: { color: '#e1e0d9' },
                                ticks: { color: '#898781', callback: moneyFmt },
                            },
                        },
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#52514e', usePointStyle: true, pointStyle: 'line' } },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: { label: (ctx) => `${ctx.dataset.label}: ${moneyFmt(ctx.parsed.y)}` },
                            },
                        },
                    },
                });
            }

            // Charts iniciados dentro de uma aba escondida (display:none) renderizam com largura 0;
            // ao trocar de aba, forçamos um resize para o Chart.js recalcular as dimensões reais.
            window.upmusicResizeEvolucaoCharts = () => {
                evolucaoChart?.resize();
                compararChart?.resize();
            };
        });
    </script>
    @endpush
</x-app-layout>
