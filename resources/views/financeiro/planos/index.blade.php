@php $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Planejamento financeiro</h2></x-slot>

    <x-page-header title="Planejamento financeiro" subtitle="Planos com comparativo Previsto x Realizado." icon="fa-chart-line">
        <x-slot name="actions">
            <a href="{{ route('financial.report') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-scale-balanced"></i> Comparativo</a>
            <a href="{{ route('plans.create') }}" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors"><i class="fa-solid fa-plus"></i> Novo plano</a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="mb-4">
        <div class="relative max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar plano" class="pl-9" />
        </div>
    </form>

    @if ($plans->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-chart-line" title="Nenhum plano" message="Crie um plano e registre os valores previstos e realizados." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Plano</th>
                <th class="px-4 py-3 font-medium">Empresa</th>
                <th class="px-4 py-3 font-medium">Período</th>
                <th class="px-4 py-3 font-medium text-right">Previsto</th>
                <th class="px-4 py-3 font-medium text-right">Realizado</th>
                <th class="px-4 py-3 font-medium text-right">Desvio</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($plans as $plan)
                @php $dev = (float) $plan->actual_sum - (float) $plan->estimated_sum; @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <p class="font-medium text-brand-ink">{{ $plan->name }}</p>
                        <p class="text-xs text-steel">{{ $plan->entries_count }} lançamentos</p>
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $plan->empresa?->corporate_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-steel">{{ $plan->period_month ? str_pad($plan->period_month, 2, '0', STR_PAD_LEFT).'/' : '' }}{{ $plan->period_year ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-steel">{{ $fmt($plan->estimated_sum) }}</td>
                    <td class="px-4 py-3 text-right text-brand-ink font-medium">{{ $fmt($plan->actual_sum) }}</td>
                    <td class="px-4 py-3 text-right font-medium {{ $dev < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $fmt($dev) }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('plans.edit', $plan) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Abrir"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="{{ route('plans.destroy', $plan) }}" data-confirm="Excluir o plano {{ $plan->name }} e seus lançamentos?">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $plans->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
