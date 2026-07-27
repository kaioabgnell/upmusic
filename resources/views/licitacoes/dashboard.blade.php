{{-- Painel de Licitações (specs/21 §9.1): abre em cima das pendências, não em um menu escondido. --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Licitações</h2></x-slot>

    <x-page-header title="Painel de Licitações" icon="fa-gavel"
        subtitle="Acervo de habilitação das empresas do grupo e análises de edital.">
        <x-slot name="actions">
            <a href="{{ route('bid.notices.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-file-contract"></i> Analisar edital
            </a>
        </x-slot>
    </x-page-header>

    {{-- Contadores clicáveis: cada um leva à lista já filtrada. --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        @php
            $cards = [
                ['label' => 'Documentos', 'value' => $counters['total'], 'icon' => 'fa-folder-open', 'tone' => 'text-brand-ink', 'status' => null],
                ['label' => 'Válidos', 'value' => $counters['validos'], 'icon' => 'fa-circle-check', 'tone' => 'text-green-600', 'status' => 'valido'],
                ['label' => 'Vencendo', 'value' => $counters['vencendo'], 'icon' => 'fa-clock', 'tone' => 'text-amber-600', 'status' => 'vencendo'],
                ['label' => 'Vencidos', 'value' => $counters['vencidos'], 'icon' => 'fa-circle-xmark', 'tone' => 'text-red-600', 'status' => 'vencido'],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ route('bid.companies.index') }}"
               class="bg-white border border-hairline rounded-xl p-4 hover:border-brand-orange transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-steel">{{ $card['label'] }}</span>
                    <i class="fa-solid {{ $card['icon'] }} {{ $card['tone'] }}"></i>
                </div>
                <p class="mt-2 text-2xl font-semibold text-brand-ink">{{ $card['value'] }}</p>
            </a>
        @endforeach

        <a href="{{ route('bid.companies.index') }}"
           class="bg-brand-ink text-white rounded-xl p-4 hover:bg-black transition-colors">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-white/60">Empresas</span>
                <i class="fa-solid fa-building-flag text-brand-orange"></i>
            </div>
            <p class="mt-2 text-2xl font-semibold">{{ $counters['empresas'] }}</p>
        </a>
    </div>

    {{-- Alertas de vencimento: vencidos primeiro, depois o menor prazo. --}}
    <div class="bg-white border border-hairline rounded-xl mb-6">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline">
            <h2 class="text-sm font-semibold text-brand-ink">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-2"></i>
                Documentos que exigem ação
                <span class="text-steel font-normal">({{ $counters['vencendo'] + $counters['vencidos'] }})</span>
            </h2>
            @if ($alerts->isNotEmpty())
                <a href="{{ route('bid.reports.index') }}" class="text-xs font-medium text-brand-orange-deep hover:underline">
                    Ver relatório completo
                </a>
            @endif
        </div>

        @if ($alerts->isEmpty())
            <x-empty-state icon="fa-circle-check" title="Nenhum vencimento à vista"
                message="Nenhum documento vencido ou a vencer nos próximos {{ config('licitacoes.expiring_days') }} dias." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-hairline text-sm">
                    <thead class="bg-surface">
                        <tr class="text-left text-steel">
                            <th class="px-4 py-2 font-medium">Empresa</th>
                            <th class="px-4 py-2 font-medium">Documento</th>
                            <th class="px-4 py-2 font-medium">Categoria</th>
                            <th class="px-4 py-2 font-medium">Vencimento</th>
                            <th class="px-4 py-2 font-medium text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($alerts as $doc)
                            <tr class="hover:bg-surface/60">
                                <td class="px-4 py-2 text-brand-ink">
                                    <a href="{{ route('bid.companies.show', $doc->bid_company_id) }}" class="hover:underline">
                                        {{ $doc->company->display_name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-brand-ink font-medium">{{ $doc->name }}</td>
                                <td class="px-4 py-2 text-steel">{{ $doc->category?->name ?? '—' }}</td>
                                <td class="px-4 py-2"><x-bid.status-badge :document="$doc" /></td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('bid.companies.show', $doc->bid_company_id) }}#documento-{{ $doc->id }}"
                                       class="inline-flex items-center gap-1.5 rounded-md border border-hairline px-3 py-1 text-xs font-medium text-brand-ink hover:bg-surface">
                                        <i class="fa-solid fa-rotate"></i> Renovar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Saúde documental por empresa --}}
        <div class="bg-white border border-hairline rounded-xl">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <h2 class="text-sm font-semibold text-brand-ink">Situação por empresa</h2>
                <a href="{{ route('bid.companies.index') }}" class="text-xs font-medium text-brand-orange-deep hover:underline">Ver todas</a>
            </div>

            @if ($health->isEmpty())
                <x-empty-state icon="fa-building-flag" title="Nenhuma empresa cadastrada"
                    message="Cadastre as empresas do grupo para começar a montar o acervo.">
                    <x-slot name="action">
                        <a href="{{ route('bid.companies.create') }}"
                           class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-plus"></i> Nova empresa
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <ul class="divide-y divide-hairline">
                    @foreach ($health as $row)
                        <li class="px-4 py-3 flex items-center gap-4">
                            <a href="{{ route('bid.companies.show', $row['company']) }}"
                               class="flex-1 min-w-0 text-sm font-medium text-brand-ink truncate hover:underline">
                                {{ $row['company']->display_name }}
                            </a>
                            <x-bid.health-bar :ok="$row['ok']" :total="$row['total']" :percent="$row['percent']" />
                        </li>
                    @endforeach
                </ul>
                <p class="px-4 py-3 border-t border-hairline text-[11px] text-steel">
                    Proporção de tipos de documento marcados como essenciais no catálogo que estão
                    cobertos por documento vigente e não vencido.
                </p>
            @endif
        </div>

        {{-- Últimas análises --}}
        <div class="bg-white border border-hairline rounded-xl">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <h2 class="text-sm font-semibold text-brand-ink">Últimas análises de edital</h2>
                <a href="{{ route('bid.notices.index') }}" class="text-xs font-medium text-brand-orange-deep hover:underline">Ver todas</a>
            </div>

            @if ($notices->isEmpty())
                <x-empty-state icon="fa-file-contract" title="Nenhum edital analisado"
                    message="Envie o PDF de um edital e descubra qual empresa está mais apta.">
                    <x-slot name="action">
                        <a href="{{ route('bid.notices.create') }}"
                           class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-plus"></i> Analisar edital
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <ul class="divide-y divide-hairline">
                    @foreach ($notices as $notice)
                        @php $top = $notice->evaluations->first(); @endphp
                        <li class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ route('bid.notices.show', $notice) }}" class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-brand-ink truncate hover:underline">{{ $notice->title }}</p>
                                    <p class="text-xs text-steel truncate">
                                        {{ $notice->agency ?: 'Órgão não identificado' }}
                                        @if ($notice->session_at) &middot; sessão {{ $notice->session_at->format('d/m/Y') }} @endif
                                    </p>
                                </a>
                                <x-badge :variant="$notice->status->badgeVariant()">{{ $notice->status->label() }}</x-badge>
                            </div>

                            @if ($top)
                                <p class="mt-1.5 text-xs text-steel">
                                    <i class="fa-solid fa-trophy text-brand-orange"></i>
                                    1º {{ $top->company?->display_name }} —
                                    <span class="font-medium text-brand-ink">{{ $top->verdict->label() }}</span>
                                    ({{ number_format((float) $top->score, 0) }})
                                </p>
                            @elseif ($notice->is_stale)
                                <p class="mt-1.5 text-xs text-red-600"><i class="fa-solid fa-plug-circle-xmark"></i> Análise interrompida — reprocessar</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
