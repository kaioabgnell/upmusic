{{-- Plano de regularização de uma empresa para um edital (specs/21 §9.6): o que providenciar,
     em ordem de urgência. Impressão e cópia direta para quem vai atrás dos documentos. --}}
@php use App\Domain\Enums\BidMatchStatus; @endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Plano de regularização</h2></x-slot>

    <x-page-header :title="$company->display_name" icon="fa-clipboard-list"
        :subtitle="'Edital: '.$notice->title">
        <x-slot name="actions">
            <a href="{{ route('bid.notices.show', $notice) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar à análise
            </a>
            <a href="{{ route('bid.companies.show', $company) }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                <i class="fa-solid fa-folder-open"></i> Abrir acervo
            </a>
        </x-slot>
    </x-page-header>

    {{-- Resumo da aptidão --}}
    @if ($evaluation)
        <div class="bg-white border border-hairline rounded-xl p-5 mb-4">
            <div class="flex flex-wrap items-center gap-4">
                <x-badge :variant="$evaluation->verdict->badgeVariant()" :icon="$evaluation->verdict->icon()">
                    {{ $evaluation->verdict->label() }}
                </x-badge>
                <span class="text-sm text-steel">
                    Score <span class="font-semibold text-brand-ink">{{ number_format((float) $evaluation->score, 0) }}</span>
                    &middot; {{ $evaluation->rank }}º no ranking
                </span>
                <span class="text-sm text-steel">
                    {{ $evaluation->met_count }} atendidos ·
                    {{ $evaluation->expiring_count }} vencendo ·
                    {{ $evaluation->missing_count }} faltando ·
                    {{ $evaluation->review_count }} a conferir
                </span>
                @if ($notice->session_at)
                    <span class="text-sm text-steel">
                        <i class="fa-solid fa-calendar-day"></i>
                        Sessão em {{ $notice->session_at->format('d/m/Y H:i') }}
                        ({{ (int) now()->diffInDays($notice->session_at, false) }} dia(s))
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if ($items->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-circle-check" title="Nada a providenciar"
                message="Todos os requisitos deste edital estão atendidos por documentos vigentes desta empresa." />
        </div>
    @else
        <div class="bg-white border border-hairline rounded-xl divide-y divide-hairline">
            @foreach ($items as $index => $item)
                @php
                    $status = $item->status;
                    $priority = match ($status) {
                        BidMatchStatus::Ausente, BidMatchStatus::Vencido => ['Bloqueia a participação', 'text-red-700 bg-red-50 border-red-200'],
                        BidMatchStatus::Vencendo => ['Renovar antes da sessão', 'text-amber-700 bg-amber-50 border-amber-200'],
                        default => ['Conferir manualmente', 'text-steel bg-surface border-hairline'],
                    };
                @endphp
                <div class="px-5 py-4 flex flex-col sm:flex-row gap-4">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-brand-ink text-white text-xs font-semibold shrink-0">
                        {{ $index + 1 }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-brand-ink">{{ $item->requirement->name }}</p>
                            @unless ($item->requirement->mandatory)
                                <x-badge variant="neutral">opcional</x-badge>
                            @endunless
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $priority[1] }}">
                                <i class="fa-solid {{ $status->icon() }}"></i> {{ $priority[0] }}
                            </span>
                        </div>

                        <p class="text-sm text-steel mt-1">{{ $item->reason }}</p>

                        @if ($item->requirement->type)
                            <p class="text-xs text-steel mt-1">
                                Tipo esperado: {{ $item->requirement->type->name }}
                                @if ($item->requirement->type->issuer) &middot; emissor: {{ $item->requirement->type->issuer }} @endif
                            </p>
                        @endif

                        <details class="mt-2">
                            <summary class="text-xs text-brand-orange-deep cursor-pointer hover:underline">
                                Ver trecho do edital @if ($item->requirement->source_page) (p. {{ $item->requirement->source_page }}) @endif
                            </summary>
                            <blockquote class="mt-2 rounded-lg bg-surface border-l-4 border-brand-orange px-4 py-2 text-xs text-brand-ink italic">
                                {{ $item->requirement->source_excerpt }}
                            </blockquote>
                        </details>
                    </div>

                    @if ($item->document)
                        <div class="shrink-0 text-right">
                            <a href="{{ route('bid.documents.file', $item->document) }}" target="_blank" rel="noopener noreferrer"
                               class="text-xs text-brand-orange-deep hover:underline">
                                <i class="fa-solid fa-file-arrow-down"></i> documento atual
                            </a>
                            <p class="text-xs text-steel mt-1">{{ $item->document->status_label }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="mt-3 text-xs text-steel">
            Ordem de urgência: primeiro o que bloqueia a habilitação, depois o que vence antes da sessão e,
            por fim, o que exige conferência humana.
        </p>
    @endif
</x-app-layout>
