@php
    $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');

    // Só entram categorias com gasto: uma fatia de R$ 0,00 não diz nada e ainda rouba uma cor da
    // legenda. Sem nenhum gasto ainda, a pizza cai para o previsto, que é o que existe para mostrar.
    $pizzaFonte = collect($byCategory)->filter(fn ($c) => $c['actual'] > 0);
    $pizzaUsaPrevisto = $pizzaFonte->isEmpty();
    $pizzaCategorias = $pizzaUsaPrevisto
        ? collect($byCategory)->filter(fn ($c) => $c['estimated'] > 0)
        : $pizzaFonte;

    $temDados = $summary['cost']['current_estimate'] > 0
        || $summary['cost']['actual'] > 0
        || $summary['revenue']['estimated'] > 0;
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Relatório — {{ $evento->name }}</h2></x-slot>

    <x-page-header title="Relatório" :subtitle="$evento->name" icon="fa-chart-column">
        <x-slot name="actions">
            <a href="{{ route('finance.export', $evento) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-file-excel"></i> Exportar
            </a>
        </x-slot>
    </x-page-header>

    @include('financeiro.eventos._tabs', ['active' => 'relatorio'])

    @unless ($temDados)
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-chart-column" title="Sem dados para o relatório"
                message="Lance receitas ou custos deste evento para os gráficos aparecerem." />
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- 1. Previsto x realizado --}}
            <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-hairline">
                    <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-chart-pie text-brand-orange mr-2"></i>Custo previsto x realizado</h3>
                    <p class="text-xs text-steel mt-0.5">
                        Previsto {{ $money($summary['cost']['current_estimate']) }} ·
                        Realizado {{ $money($summary['cost']['actual']) }}
                    </p>
                </div>
                <div class="p-4" style="height: 280px"><canvas id="grafico-previsto-realizado"></canvas></div>
            </div>

            {{-- 2. Por categoria --}}
            <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-hairline">
                    <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-chart-pie text-brand-orange mr-2"></i>Custo por categoria</h3>
                    <p class="text-xs text-steel mt-0.5">
                        {{ $pizzaUsaPrevisto ? 'Valor previsto — ainda não há gasto realizado.' : 'Onde o dinheiro foi, de fato.' }}
                    </p>
                </div>
                @if ($pizzaCategorias->isEmpty())
                    <x-empty-state icon="fa-table-list" title="Sem custos por categoria"
                        message="Classifique as linhas de custo por item para ver a divisão." />
                @else
                    <div class="p-4" style="height: 280px"><canvas id="grafico-categorias"></canvas></div>
                @endif
            </div>

            {{-- 3. Orçado x previsto x gasto --}}
            <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-hairline">
                    <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-chart-simple text-brand-orange mr-2"></i>Orçado x previsto x gasto</h3>
                    <p class="text-xs text-steel mt-0.5">
                        @if ($sheet->uses_second_estimate)
                            Orçado é o Previsto 1; previsto é o valor vigente, já refinado pelo Previsto 2.
                        @else
                            Com o "Previsto 2" desligado, orçado e previsto são o mesmo número.
                        @endif
                    </p>
                </div>
                <div class="p-4" style="height: 280px"><canvas id="grafico-orcado-gasto"></canvas></div>
            </div>

            {{-- 4. Evolução de caixa --}}
            <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-hairline">
                    <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-money-bill-trend-up text-brand-orange mr-2"></i>Evolução de caixa</h3>
                    <p class="text-xs text-steel mt-0.5">Entradas e saídas por mês, com o saldo acumulado.</p>
                </div>
                @if (empty($cashFlow))
                    <x-empty-state icon="fa-calendar-day" title="Sem movimento com data"
                        message="Registre a data dos pagamentos e dos recebimentos para ver a evolução." />
                @else
                    <div class="p-4" style="height: 280px"><canvas id="grafico-caixa"></canvas></div>
                @endif
            </div>
        </div>
    @endunless

    @if ($temDados)
    @push('scripts')
    <script>
        // app.js é carregado como <script type="module"> (Vite) e por isso é sempre adiado — executa
        // depois deste script inline. Sem esperar o DOM, window.Chart ainda não existe aqui.
        document.addEventListener('DOMContentLoaded', function () {
            const ink = '#000000';
            const orange = '#ff8c1e';
            const grid = '#e1e0d9';
            const steel = '#898781';
            // Paleta das fatias por categoria: laranja da marca primeiro (é a cor de destaque) e
            // depois tons que se distinguem em tela e no print da prestação de contas.
            const palette = ['#ff8c1e', '#000000', '#6b7280', '#c2410c', '#0f766e', '#7c3aed',
                             '#b45309', '#1d4ed8', '#be123c', '#4d7c0f', '#a16207', '#155e75'];

            const money = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const moneyShort = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 });

            const chart = (id, config) => {
                const canvas = document.getElementById(id);
                return canvas ? new window.Chart(canvas, config) : null;
            };

            // Pizza: a fatia já É o valor, então o rótulo mostra o valor e a fatia do total.
            const pieTooltip = {
                callbacks: {
                    label: (ctx) => {
                        const total = ctx.dataset.data.reduce((sum, v) => sum + v, 0);
                        const share = total > 0 ? ` (${(ctx.parsed / total * 100).toFixed(1)}%)` : '';
                        return `${ctx.label}: ${money(ctx.parsed)}${share}`;
                    },
                },
            };
            const moneyAxis = {
                x: { grid: { display: false }, ticks: { color: steel } },
                y: { grid: { color: grid }, ticks: { color: steel, callback: moneyShort } },
            };

            chart('grafico-previsto-realizado', {
                type: 'doughnut',
                data: {
                    labels: ['Previsto', 'Realizado'],
                    datasets: [{
                        data: [{{ (float) $summary['cost']['current_estimate'] }}, {{ (float) $summary['cost']['actual'] }}],
                        backgroundColor: [ink, orange],
                        borderColor: '#fcfcfb',
                        borderWidth: 2,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: '#52514e', usePointStyle: true } }, tooltip: pieTooltip },
                },
            });

            chart('grafico-categorias', {
                type: 'pie',
                data: {
                    labels: @json($pizzaCategorias->pluck('label')->values()),
                    datasets: [{
                        data: @json($pizzaCategorias->pluck($pizzaUsaPrevisto ? 'estimated' : 'actual')->values()),
                        backgroundColor: palette,
                        borderColor: '#fcfcfb',
                        borderWidth: 2,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: '#52514e', usePointStyle: true, boxWidth: 10 } }, tooltip: pieTooltip },
                },
            });

            chart('grafico-orcado-gasto', {
                type: 'bar',
                data: {
                    labels: ['Orçado', 'Previsto', 'Gasto'],
                    datasets: [{
                        label: 'Custo',
                        data: [
                            {{ (float) $summary['cost']['estimated_1'] }},
                            {{ (float) $summary['cost']['current_estimate'] }},
                            {{ (float) $summary['cost']['actual'] }},
                        ],
                        backgroundColor: [ink, '#6b7280', orange],
                        borderRadius: 4,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: moneyAxis,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => money(ctx.parsed.y) } },
                    },
                },
            });

            chart('grafico-caixa', {
                type: 'bar',
                data: {
                    labels: @json(collect($cashFlow)->pluck('label')->values()),
                    datasets: [
                        { label: 'Entradas', data: @json(collect($cashFlow)->pluck('in')->values()), backgroundColor: orange, borderRadius: 4, order: 2 },
                        { label: 'Saídas', data: @json(collect($cashFlow)->pluck('out')->values()), backgroundColor: ink, borderRadius: 4, order: 2 },
                        {
                            label: 'Saldo acumulado',
                            data: @json(collect($cashFlow)->pluck('balance')->values()),
                            type: 'line',
                            borderColor: '#0f766e',
                            backgroundColor: '#0f766e',
                            borderWidth: 2,
                            pointRadius: 3,
                            tension: 0,
                            order: 1,
                        },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: moneyAxis,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#52514e', usePointStyle: true } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y)}` } },
                    },
                },
            });
        });
    </script>
    @endpush
    @endif
</x-app-layout>
