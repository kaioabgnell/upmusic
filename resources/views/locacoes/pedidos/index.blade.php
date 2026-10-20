@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Pedidos de Locação</h2></x-slot>

    <x-page-header title="Pedidos de Locação" icon="fa-file-invoice-dollar" subtitle="Receita, custo e resultado de cada pedido — Gestão de Locações.">
        <x-slot name="actions">
            <a href="{{ route('rentals.board') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-table-columns"></i> Painel
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label class="text-xs text-steel">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Código, cliente, local"
                   class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
        </div>
        <div class="w-48">
            <label class="text-xs text-steel">Etapa</label>
            <x-form.select name="status[]" class="mt-1">
                <option value="">Todas</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div class="w-56">
            <label class="text-xs text-steel">Cliente</label>
            <x-form.select name="empresa_id" class="mt-1">
                <option value="">Todos</option>
                @foreach ($empresas as $e)
                    <option value="{{ $e->id }}" @selected((string) request('empresa_id') === (string) $e->id)>{{ $e->trade_name ?: $e->corporate_name }}</option>
                @endforeach
            </x-form.select>
        </div>
        <label class="inline-flex items-center gap-1.5 text-sm text-steel pb-2">
            <input type="checkbox" name="receivable" value="1" @checked(request('receivable')) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            Com saldo a receber
        </label>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
        @if (request()->hasAny(['search', 'status', 'empresa_id', 'receivable']))
            <a href="{{ route('rentals.orders.index') }}" class="text-sm text-steel hover:text-brand-ink">Limpar</a>
        @endif
    </form>

    @if ($orders->isEmpty())
        <x-data-table>
            <x-empty-state icon="fa-file-invoice-dollar" title="Nenhum pedido encontrado"
                message="Crie o primeiro pedido de locação pelo Painel." />
        </x-data-table>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Pedido</th>
                <th class="px-4 py-3 font-medium">Cliente</th>
                <th class="px-4 py-3 font-medium">Período</th>
                <th class="px-4 py-3 font-medium">Etapa</th>
                <th class="px-4 py-3 font-medium text-right">Receita prev. / real.</th>
                <th class="px-4 py-3 font-medium text-right">Recebido</th>
                <th class="px-4 py-3 font-medium text-right">A receber</th>
            </x-slot>

            @foreach ($orders as $order)
                <tr class="hover:bg-surface cursor-pointer" onclick="window.location.href='{{ route('rentals.board') }}?abrir={{ $order->id }}'">
                    <td class="px-4 py-3 font-medium text-brand-ink">{{ $order->code }}</td>
                    <td class="px-4 py-3 text-brand-ink">{{ $order->clientLabel() ?: '—' }}</td>
                    <td class="px-4 py-3 text-steel whitespace-nowrap">
                        {{ $order->start_at?->format('d/m H:i') }} — {{ $order->end_at?->format('d/m H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$order->status->color()" :icon="$order->status->icon()">{{ $order->status->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div>{{ $money($order->revenue_estimated) }}</div>
                        <div class="text-xs text-steel">{{ $order->revenue_actual === null ? '—' : $money($order->revenue_actual) }}</div>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ $money($order->receipts->sum('amount')) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @php $receivable = (float) ($order->revenue_actual ?? $order->revenue_estimated) - (float) $order->receipts->sum('amount'); @endphp
                        <span class="{{ $receivable > 0 ? 'text-brand-orange-deep font-medium' : 'text-brand-ink' }}">{{ $money($receivable) }}</span>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-4 text-xs text-steel">
                        <span>{{ $totals['count'] }} pedido(s)</span>
                        <span>Receita prevista: <strong class="text-brand-ink">{{ $money($totals['revenue_estimated']) }}</strong></span>
                        <span>Recebido: <strong class="text-brand-ink">{{ $money($totals['received']) }}</strong></span>
                        <span>A receber: <strong class="text-brand-orange-deep">{{ $money($totals['receivable']) }}</strong></span>
                        <span>Resultado previsto: <strong class="text-brand-ink">{{ $money($totals['result_estimated']) }}</strong></span>
                    </div>
                    {{ $orders->links() }}
                </div>
            </x-slot>
        </x-data-table>
    @endif
</x-app-layout>
