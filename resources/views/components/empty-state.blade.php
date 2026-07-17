@props([
    'icon' => 'fa-inbox',
    'title' => 'Nada por aqui ainda',
    'message' => null,
])

{{-- Estado vazio com CTA opcional (slot `action`). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-16 px-6']) }}>
    <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-surface text-steel mb-4">
        <i class="fa-solid {{ $icon }} text-xl"></i>
    </span>
    <h3 class="text-base font-semibold text-brand-ink">{{ $title }}</h3>
    @if($message)
        <p class="text-sm text-steel mt-1 max-w-sm">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
