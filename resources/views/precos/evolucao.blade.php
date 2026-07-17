@php
    $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $chartSeries = $history->reverse()->values(); // cronológico para o gráfico
    $maxPrice = $chartSeries->max('price') ?: 1;
    $minPrice = $chartSeries->min('price') ?: 0;
    $range = max($maxPrice - $minPrice, 1);
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Evolução de preços</h2></x-slot>

    <x-page-header title="Evolução de preços" subtitle="Histórico de preços por categoria de fornecedor, em todos os eventos." icon="fa-chart-line">
        <x-slot name="actions">
            <a href="{{ route('prices.categorias.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-arrow-left"></i> Banco de preços</a>
        </x-slot>
    </x-page-header>

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
    </form>

    @if (! $selectedCategoria)
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-chart-line" title="Selecione uma categoria" message="Escolha uma categoria acima para ver a evolução de preços." />
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Evolução da categoria --}}
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
                    {{-- Gráfico de linha simples via SVG --}}
                    <div class="mb-6 overflow-x-auto">
                        <svg viewBox="0 0 {{ max(300, $chartSeries->count() * 80) }} 150" class="w-full" style="min-width: {{ $chartSeries->count() * 80 }}px" preserveAspectRatio="none">
                            @php
                                $w = max(300, $chartSeries->count() * 80);
                                $points = $chartSeries->values()->map(function ($p, $i) use ($chartSeries, $w, $minPrice, $range) {
                                    $x = $chartSeries->count() > 1 ? ($i / ($chartSeries->count() - 1)) * ($w - 40) + 20 : $w / 2;
                                    $y = 130 - (($p['price'] - $minPrice) / $range) * 100;
                                    return "$x,$y";
                                })->implode(' ');
                            @endphp
                            <polyline points="{{ $points }}" fill="none" stroke="#ff8c1e" stroke-width="2.5" />
                            @foreach ($chartSeries as $i => $p)
                                @php
                                    $x = $chartSeries->count() > 1 ? ($i / ($chartSeries->count() - 1)) * ($w - 40) + 20 : $w / 2;
                                    $y = 130 - (($p['price'] - $minPrice) / $range) * 100;
                                @endphp
                                <text x="{{ $x }}" y="{{ $y - 10 }}" font-size="10" font-weight="600" fill="#000000" text-anchor="middle">{{ $money($p['price']) }}</text>
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#000000" />
                                <text x="{{ $x }}" y="145" font-size="10" fill="#5a5a5c" text-anchor="middle">{{ $p['reference_date_br'] }}</text>
                            @endforeach
                        </svg>
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

            {{-- Comparação entre fornecedores --}}
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
    @endif
</x-app-layout>
