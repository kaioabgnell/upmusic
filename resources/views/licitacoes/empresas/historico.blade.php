{{-- Histórico de versões de um documento (specs/21 §9.3). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Histórico do documento</h2></x-slot>

    <x-page-header :title="$document->name" icon="fa-clock-rotate-left"
        :subtitle="$document->company->display_name.' · '.($document->category?->name ?? 'sem categoria')">
        <x-slot name="actions">
            <a href="{{ route('bid.companies.show', $document->bid_company_id) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar ao acervo
            </a>
        </x-slot>
    </x-page-header>

    <div class="bg-white border border-hairline rounded-xl divide-y divide-hairline">
        {{-- Versão vigente --}}
        <div class="px-4 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <x-badge variant="orange">Versão vigente</x-badge>
                    <span class="font-medium text-brand-ink truncate">{{ $document->original_name }}</span>
                </div>
                <p class="text-xs text-steel mt-1">
                    Enviado por {{ $document->uploader?->name ?? 'sistema' }} em {{ $document->created_at->format('d/m/Y H:i') }}
                    @if ($document->control_code) &middot; código {{ $document->control_code }} @endif
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <x-bid.status-badge :document="$document" />
                <a href="{{ route('bid.documents.file', $document) }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                    <i class="fa-solid fa-file-arrow-down"></i>
                </a>
            </div>
        </div>

        {{-- Versões anteriores --}}
        @forelse ($versions as $version)
            @php
                $lateDays = $version->expires_at && $version->superseded_at && $version->superseded_at->gt($version->expires_at)
                    ? $version->expires_at->diffInDays($version->superseded_at)
                    : null;
            @endphp
            <div class="px-4 py-4 flex flex-col sm:flex-row sm:items-center gap-3 bg-surface/40">
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-brand-ink truncate">{{ $version->original_name }}</p>
                    <p class="text-xs text-steel mt-1">
                        Validade {{ $version->expires_at?->format('d/m/Y') ?? 'sem validade' }}
                        &middot; substituído em {{ $version->superseded_at?->format('d/m/Y') }}
                        @if ($lateDays)
                            &middot; <span class="text-red-600">renovado {{ $lateDays }} dia(s) após o vencimento</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('bid.documents.file', $version) }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-white hover:text-brand-ink shrink-0">
                    <i class="fa-solid fa-file-arrow-down"></i>
                </a>
            </div>
        @empty
            <x-empty-state icon="fa-clock-rotate-left" title="Sem versões anteriores"
                message="Este documento ainda não passou por renovação." />
        @endforelse
    </div>
</x-app-layout>
