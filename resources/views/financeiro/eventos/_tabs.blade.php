@props(['evento', 'sheet', 'active'])

{{-- Barra de abas da planilha do evento (padrão Pipefy da specs/02): Resumo | Receitas | Custos. --}}
@php
    $tabs = [
        ['key' => 'resumo',   'label' => 'Resumo Geral', 'icon' => 'fa-chart-pie',  'route' => route('finance.show', $evento)],
        ['key' => 'receitas', 'label' => 'Receitas',     'icon' => 'fa-arrow-trend-up', 'route' => route('finance.revenues.index', $evento)],
        ['key' => 'custos',   'label' => 'Custos',       'icon' => 'fa-arrow-trend-down', 'route' => route('finance.costs.index', $evento)],
    ];
@endphp

<div class="flex items-center justify-between gap-4 border-b border-hairline mb-6 overflow-x-auto">
    <nav class="flex items-center gap-1">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['route'] }}"
               class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap
                      {{ $active === $tab['key']
                            ? 'border-brand-orange text-brand-ink'
                            : 'border-transparent text-steel hover:text-brand-ink' }}">
                <i class="fa-solid {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="flex items-center gap-2 pb-2 shrink-0">
        @if ($sheet->isClosed())
            <x-badge variant="dark" icon="fa-lock">Prestação de contas fechada</x-badge>
        @elseif ($sheet->uses_second_estimate)
            <x-badge variant="orange" icon="fa-layer-group">Previsto 2 ativo</x-badge>
        @endif
    </div>
</div>
