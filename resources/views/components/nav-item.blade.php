@props([
    'route' => null,   // nome da rota (ex.: boards.index)
    'pattern' => null,  // padrão para active-state (ex.: boards.*); default = base da rota
    'icon' => 'fa-circle',
    'label' => '',
])

@php
    $exists = $route && \Illuminate\Support\Facades\Route::has($route);
    $href = $exists ? route($route) : '#';
    $pattern = $pattern ?? ($route ? \Illuminate\Support\Str::before($route, '.') . '.*' : null);
    $active = $pattern && request()->routeIs($pattern);
@endphp

<a href="{{ $href }}"
   @unless($exists) aria-disabled="true" title="Disponível em fase futura" @endunless
   @class([
        'group flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors',
        'bg-brand-orange text-brand-ink font-semibold' => $active,
        'text-white/70 hover:text-white hover:bg-white/10' => ! $active && $exists,
        'text-white/30 cursor-not-allowed' => ! $exists,
   ])>
    <i class="fa-solid {{ $icon }} w-5 text-center {{ $active ? 'text-brand-ink' : '' }}"></i>
    <span>{{ $label }}</span>
</a>
