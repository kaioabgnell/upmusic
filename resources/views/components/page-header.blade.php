@props([
    'title' => '',
    'subtitle' => null,
    'icon' => null,
])

{{-- Cabeçalho de página padrão SaaS: título à esquerda, ações à direita (slot `actions`). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6']) }}>
    <div class="flex items-start gap-3 min-w-0">
        @if($icon)
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-brand-ink text-white shrink-0">
                <i class="fa-solid {{ $icon }}"></i>
            </span>
        @endif
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-semibold text-brand-ink truncate">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-sm text-steel mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex items-center gap-2 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
