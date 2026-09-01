@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Financeiro dos Eventos</h2></x-slot>

    <x-page-header title="Financeiro dos Eventos" icon="fa-file-invoice-dollar"
        subtitle="Previsto x realizado por evento — substitui a planilha FINANCEIRO - MODELO." />

    {{-- Filtros server-side (padrão do projeto) --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label class="text-xs text-steel">Buscar evento</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome do evento"
                   class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
        </div>
        <div class="w-56">
            <label class="text-xs text-steel">Situação da planilha</label>
            <x-form.select name="status" class="mt-1">
                <option value="">Todas</option>
                <option value="aberto" @selected(request('status') === 'aberto')>Aberta</option>
                <option value="fechado" @selected(request('status') === 'fechado')>Fechada</option>
                <option value="sem_planilha" @selected(request('status') === 'sem_planilha')>Sem planilha</option>
            </x-form.select>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
        @if (request()->hasAny(['search', 'status']))
            <a href="{{ route('finance.index') }}" class="text-sm text-steel hover:text-brand-ink">Limpar</a>
        @endif
    </form>

    @if ($events->isEmpty())
        <x-data-table>
            <x-empty-state icon="fa-calendar-days" title="Nenhum evento encontrado"
                message="O financeiro é organizado por evento. Cadastre um evento para começar." />
        </x-data-table>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Evento</th>
                <th class="px-4 py-3 font-medium">Período</th>
                <th class="px-4 py-3 font-medium text-right">Receita prev. / real.</th>
                <th class="px-4 py-3 font-medium text-right">Custo prev. / real.</th>
                <th class="px-4 py-3 font-medium text-right">Resultado</th>
                <th class="px-4 py-3 font-medium">Pago</th>
                <th class="px-4 py-3"></th>
            </x-slot>

            @foreach ($events as $event)
                @php
                    $t = $event->financeSheet ? ($totals[$event->financeSheet->id] ?? null) : null;
                    $resultado = $t['result_actual'] ?? null;
                @endphp
                <tr class="hover:bg-surface">
                    <td class="px-4 py-3">
                        <a href="{{ route('finance.show', $event) }}" class="font-medium text-brand-ink hover:text-brand-orange-deep">
                            {{ $event->name }}
                        </a>
                        <div class="mt-1 flex items-center gap-2">
                            @if (! $event->financeSheet)
                                <x-badge>Sem planilha</x-badge>
                            @elseif ($event->financeSheet->status->value === 'fechado')
                                <x-badge variant="dark" icon="fa-lock">Fechada</x-badge>
                            @else
                                <x-badge variant="orange">{{ $t['items'] ?? 0 }} linha(s)</x-badge>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-steel whitespace-nowrap">
                        {{ $event->start_date?->format('d/m/Y') }} — {{ $event->end_date?->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div>{{ $money($t['revenue_estimated'] ?? 0) }}</div>
                        <div class="text-xs text-steel">{{ $money($t['revenue_actual'] ?? 0) }}</div>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div>{{ $money($t['cost_estimated'] ?? 0) }}</div>
                        <div class="text-xs text-steel">{{ $money($t['cost_actual'] ?? 0) }}</div>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap font-semibold
                               {{ $resultado !== null && $resultado < 0 ? 'text-red-600' : 'text-brand-ink' }}">
                        {{ $money($resultado ?? 0) }}
                        <div class="text-xs font-normal text-steel">prev. {{ $money($t['result_estimated'] ?? 0) }}</div>
                    </td>
                    <td class="px-4 py-3">
                        @if (($t['paid_pct'] ?? null) !== null)
                            <div class="w-28">
                                <div class="h-1.5 rounded-full bg-hairline overflow-hidden">
                                    <div class="h-full bg-brand-orange" style="width: {{ min(100, $t['paid_pct']) }}%"></div>
                                </div>
                                <div class="mt-1 text-xs text-steel">{{ number_format($t['paid_pct'], 1, ',', '.') }}%</div>
                            </div>
                        @else
                            <span class="text-xs text-steel">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('finance.costs.index', $event) }}"
                           class="inline-flex items-center gap-1.5 rounded-md border border-hairline px-3 py-1.5 text-xs font-medium text-brand-ink hover:bg-surface">
                            <i class="fa-solid fa-table-list"></i> Custos
                        </a>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $events->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
