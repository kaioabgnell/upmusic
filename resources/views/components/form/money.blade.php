@props([
    'name',
    'value' => null,
    'disabled' => false,
])

{{-- Campo monetário BRL. Prefixo R$ + input numérico (inputmode decimal). --}}
<div class="relative">
    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
    <input type="text"
           name="{{ $name }}"
           value="{{ $value }}"
           inputmode="decimal"
           placeholder="0,00"
           {{ $disabled ? 'disabled' : '' }}
           {!! $attributes->merge(['class' => 'w-full pl-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm disabled:bg-gray-50']) !!}>
</div>
