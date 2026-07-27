@props(['document'])

{{-- Badge de vigência do documento (specs/21 §9). O status é sempre calculado na leitura. --}}
@php
    $status = $document->status;
    $critical = $document->is_critical;
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium '
        . $status->classes()
        . ($critical ? ' ring-1 ring-amber-500' : ''),
]) }} title="{{ $document->expires_at ? 'Vence em '.$document->expires_at->format('d/m/Y') : 'Documento sem data de validade' }}">
    <i class="fa-solid {{ $status->icon() }} text-[10px]"></i>
    {{ $document->status_label }}
</span>
