@php
    $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $maxCat = collect($byCategory)->flatMap(fn ($r) => [$r['estimated'], $r['actual']])->max() ?: 1;
    $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Comparativo Previsto x Realizado</h2></x-slot>

    <x-page-header title="Comparativo Previsto x Realizado" subtitle="Acompanhamento financeiro consolidado." icon="fa-scale-balanced">
        <x-slot name="actions">
            <a href="{{ route('financial.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-file-export"></i> Exportar CSV</a>
        </x-slot>
    </x-page-header>

    {{-- Filtros --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 bg-white border border-hairline rounded-xl p-4">
        <div>
            <label class="text-xs text-steel">Plano</label>
            <select name="plan_id" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                <option value="">Todos</option>
                @foreach ($plans as $p)<option value="{{ $p->id }}" @selected($filters['plan_id']==$p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-steel">Empresa</label>
            <select name="empresa_id" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                <option value="">Todas</option>
                @foreach ($empresas as $e)<option value="{{ $e->id }}" @selected($filters['empresa_id']==$e->id)>{{ $e->corporate_name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-steel">Categoria</label>
            <select name="category" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                <option value="">Todas</option>
                @foreach ($categories as $c)<option value="{{ $c }}" @selected($filters['category']===$c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-steel">Mês</label>
            <select name="month" class="block mt-1 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                <option value="">—</option>
                @foreach ($meses as $n => $m)<option value="{{ $n }}" @selected($filters['month']==$n)>{{ $m }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-steel">Ano</label>
            <input name="year" type="number" value="{{ $filters['year'] }}" placeholder="2026" class="block mt-1 h-9 w-24 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
        </div>
        <button type="submit" class="h-9 rounded-md bg-brand-ink px-4 text-sm font-medium text-white hover:bg-black"><i class="fa-solid fa-filter"></i> Aplicar</button>
        @if (array_filter($filters))<a href="{{ route('financial.report') }}" class="h-9 inline-flex items-center px-3 text-sm text-steel hover:text-brand-ink">Limpar</a>@endif
    </form>

    {{-- Cards de resumo --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-hairline rounded-xl p-5">
            <p class="text-sm text-steel">Previsto</p>
            <p class="text-2xl font-semibold text-brand-ink mt-1">{{ $fmt($summary['estimated']) }}</p>
        </div>
        <div class="bg-white border border-hairline rounded-xl p-5">
            <p class="text-sm text-steel">Realizado</p>
            <p class="text-2xl font-semibold text-brand-ink mt-1">{{ $fmt($summary['actual']) }}</p>
        </div>
        <div class="bg-white border border-hairline rounded-xl p-5">
            <p class="text-sm text-steel">Desvio</p>
            <p class="text-2xl font-semibold mt-1 {{ $summary['deviation'] < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $fmt($summary['deviation']) }}</p>
        </div>
        <div class="bg-white border border-hairline rounded-xl p-5">
            <p class="text-sm text-steel">% Realização</p>
            <p class="text-2xl font-semibold text-brand-ink mt-1">{{ $summary['pct'] !== null ? $summary['pct'].'%' : '—' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Gráfico de barras por categoria --}}
        <div class="bg-white border border-hairline rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-brand-ink">Por categoria</h3>
                <div class="flex items-center gap-3 text-xs text-steel">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-brand-ink"></span> Previsto</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-brand-orange"></span> Realizado</span>
                </div>
            </div>
            @forelse ($byCategory as $row)
                <div class="mb-4">
                    <div class="flex justify-between text-xs text-brand-ink mb-1">
                        <span class="font-medium">{{ $row['label'] }}</span>
                        <span class="text-steel">{{ $fmt($row['actual']) }} / {{ $fmt($row['estimated']) }}</span>
                    </div>
                    <div class="space-y-1">
                        <div class="h-3 rounded-sm bg-brand-ink" style="width: {{ max(2, $row['estimated'] / $maxCat * 100) }}%"></div>
                        <div class="h-3 rounded-sm bg-brand-orange" style="width: {{ max(2, $row['actual'] / $maxCat * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-steel">Sem dados para os filtros.</p>
            @endforelse
        </div>

        {{-- Por empresa --}}
        <div class="bg-white border border-hairline rounded-xl p-6">
            <h3 class="font-semibold text-brand-ink mb-4">Por empresa</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-steel">
                        <tr>
                            <th class="py-2 font-medium">Empresa</th>
                            <th class="py-2 font-medium text-right">Previsto</th>
                            <th class="py-2 font-medium text-right">Realizado</th>
                            <th class="py-2 font-medium text-right">Desvio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse ($byEmpresa as $row)
                            <tr>
                                <td class="py-2 text-brand-ink">{{ $row['label'] }}</td>
                                <td class="py-2 text-right text-steel">{{ $fmt($row['estimated']) }}</td>
                                <td class="py-2 text-right text-brand-ink">{{ $fmt($row['actual']) }}</td>
                                <td class="py-2 text-right {{ $row['deviation'] < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $fmt($row['deviation']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-steel">Sem dados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
