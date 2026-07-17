@props([
    'variant' => 'neutral', // neutral | orange | success | danger | dark
    'icon' => null,
])

@php
    $variants = [
        'neutral' => 'bg-gray-100 text-gray-700',
        'orange'  => 'bg-brand-orange/15 text-brand-orange-deep',
        'success' => 'bg-green-100 text-green-700',
        'danger'  => 'bg-red-100 text-red-700',
        'dark'    => 'bg-brand-ink text-white',
    ];
    $classes = $variants[$variant] ?? $variants['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium $classes"]) }}>
    @if($icon)<i class="fa-solid {{ $icon }} text-[10px]"></i>@endif
    {{ $slot }}
</span>
