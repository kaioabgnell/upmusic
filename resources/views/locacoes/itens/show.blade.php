@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">{{ $item->name }}</h2></x-slot>

    <x-page-header :title="$item->name" :subtitle="$item->sku" icon="fa-boxes-stacked">
        <x-slot name="actions">
            <a href="{{ route('rentals.items.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
            @can('update', $item)
                <a href="{{ route('rentals.items.edit', $item) }}" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                    <i class="fa-solid fa-pen"></i> Editar
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-hairline rounded-xl p-4">
            <p class="text-xs text-steel">Quantidade total</p>
            <p class="text-xl font-semibold text-brand-ink mt-1">{{ $item->quantity }}</p>
        </div>
        <div class="bg-white border border-hairline rounded-xl p-4">
            <p class="text-xs text-steel">Disponível hoje</p>
            <p class="text-xl font-semibold text-brand-ink mt-1">{{ $available }}</p>
        </div>
        <div class="bg-white border border-hairline rounded-xl p-4">
            <p class="text-xs text-steel">Valor de locação (diária)</p>
            <p class="text-xl font-semibold text-brand-ink mt-1">{{ $money($item->rental_price) }}</p>
        </div>
        @if ($withCost)
            <div class="bg-white border border-hairline rounded-xl p-4">
                <p class="text-xs text-steel">Valor do produto</p>
                <p class="text-xl font-semibold text-brand-ink mt-1">{{ $money($item->unit_cost) }}</p>
            </div>
            <div class="bg-white border border-hairline rounded-xl p-4">
                <p class="text-xs text-steel">Investimento total</p>
                <p class="text-xl font-semibold text-brand-ink mt-1">{{ $money($item->total_investment) }}</p>
            </div>
            <div class="bg-white border border-hairline rounded-xl p-4">
                <p class="text-xs text-steel">Data de compra</p>
                <p class="text-xl font-semibold text-brand-ink mt-1">{{ optional($item->purchase_date)->format('d/m/Y') ?? '—' }}</p>
            </div>
        @endif
    </div>

    @if ($item->description)
        <div class="bg-white border border-hairline rounded-xl p-4 mb-6">
            <p class="text-xs font-semibold text-brand-ink mb-1">Descrição</p>
            <p class="text-sm text-steel">{{ $item->description }}</p>
        </div>
    @endif

    <x-data-table>
        <x-slot name="head">
            <th class="px-4 py-3 font-medium">Pedido</th>
            <th class="px-4 py-3 font-medium">Etapa</th>
            <th class="px-4 py-3 font-medium">Período</th>
        </x-slot>
        @forelse ($upcoming as $line)
            <tr class="hover:bg-surface">
                <td class="px-4 py-3"><a href="{{ route('rentals.board') }}?abrir={{ $line->order->id }}" class="font-medium text-brand-ink hover:text-brand-orange-deep">{{ $line->order->code }}</a></td>
                <td class="px-4 py-3"><x-badge :variant="$line->order->status->color()">{{ $line->order->status->label() }}</x-badge></td>
                <td class="px-4 py-3 text-steel whitespace-nowrap">{{ $line->order->start_at?->format('d/m H:i') }} — {{ $line->order->end_at?->format('d/m H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty-state icon="fa-calendar-check" title="Sem reservas ativas" message="Este item não está reservado em nenhum pedido no momento." /></td></tr>
        @endforelse
    </x-data-table>
</x-app-layout>
