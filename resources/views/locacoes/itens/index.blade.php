@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Itens — Gestão de Locações</h2></x-slot>

    <div x-data="rentalsItems({ urls: { itemBase: '{{ url('locacoes/itens') }}' } })">
        <x-page-header title="Itens do inventário" icon="fa-boxes-stacked" subtitle="LED, mesas, telões, som e outros equipamentos próprios.">
            <x-slot name="actions">
                <a href="{{ route('rentals.board') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                    <i class="fa-solid fa-table-columns"></i> Painel
                </a>
                @can('create', \App\Models\RentalItem::class)
                    <a href="{{ route('rentals.items.create') }}" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Novo item
                    </a>
                @endcan
            </x-slot>
        </x-page-header>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[220px]">
                <label class="text-xs text-steel">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome ou código"
                       class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>
            <div class="w-48">
                <label class="text-xs text-steel">Status</label>
                <x-form.select name="status" class="mt-1">
                    <option value="active" @selected($status === 'active')>Ativos</option>
                    <option value="inactive" @selected($status === 'inactive')>Inativos</option>
                    @if ($withCost)
                        <option value="deleted" @selected($status === 'deleted')>Excluídos</option>
                    @endif
                </x-form.select>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
        </form>

        @if ($items->isEmpty())
            <x-data-table>
                <x-empty-state icon="fa-boxes-stacked" title="Nenhum item cadastrado"
                    message="Cadastre LED, mesas, telões, som e outros itens próprios para locação." />
            </x-data-table>
        @else
            <x-data-table>
                <x-slot name="head">
                    <th class="px-4 py-3 font-medium">Item</th>
                    <th class="px-4 py-3 font-medium text-right">Quantidade</th>
                    <th class="px-4 py-3 font-medium text-right">Disponível hoje</th>
                    <th class="px-4 py-3 font-medium text-right">Valor de locação</th>
                    @if ($withCost)
                        <th class="px-4 py-3 font-medium text-right">Valor do produto</th>
                        <th class="px-4 py-3 font-medium text-right">Investimento</th>
                    @endif
                    <th class="px-4 py-3"></th>
                </x-slot>

                @foreach ($items as $item)
                    <tr class="hover:bg-surface">
                        <td class="px-4 py-3">
                            <a href="{{ route('rentals.items.show', $item) }}" class="font-medium text-brand-ink hover:text-brand-orange-deep">{{ $item->name }}</a>
                            @if ($item->sku)<div class="text-xs text-steel">{{ $item->sku }}</div>@endif
                            @if (! $item->active)<x-badge class="mt-1">Inativo</x-badge>@endif
                            @if ($item->trashed())<x-badge variant="dark" class="mt-1">Excluído — {{ $item->deletion_reason?->label() }}</x-badge>@endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right">{{ $available[$item->id] ?? $item->quantity }}</td>
                        <td class="px-4 py-3 text-right">{{ $money($item->rental_price) }}</td>
                        @if ($withCost)
                            <td class="px-4 py-3 text-right">{{ $money($item->unit_cost) }}</td>
                            <td class="px-4 py-3 text-right">{{ $money($item->total_investment) }}</td>
                        @endif
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('update', $item)
                                <a href="{{ route('rentals.items.edit', $item) }}" class="text-steel hover:text-brand-ink mr-2"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                            @can('delete', $item)
                                @unless ($item->trashed())
                                    <button type="button" @click="deleteItem({{ $item->id }}, '{{ addslashes($item->name) }}')" class="text-steel hover:text-red-600">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach

                <x-slot name="footer">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-steel">
                            {{ $items->total() }} item(ns)
                            @if ($withCost && $totalInvestment !== null)
                                · Investimento total: <strong class="text-brand-ink">{{ $money($totalInvestment) }}</strong>
                            @endif
                        </div>
                        {{ $items->links() }}
                    </div>
                </x-slot>
            </x-data-table>
        @endif
    </div>
</x-app-layout>
