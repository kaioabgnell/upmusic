@props(['variant' => 'preta'])

{{-- Logo upMusic. variant: 'preta' (fonte preta, fundo claro) | 'branca' (fonte branca, fundo escuro) --}}
<img src="{{ asset('img/logo-' . $variant . '.png') }}"
     alt="UpMusic"
     {{ $attributes->merge(['class' => 'h-8 w-auto']) }}>
