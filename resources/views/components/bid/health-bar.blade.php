@props(['ok' => 0, 'total' => 0, 'percent' => 0])

{{-- Saúde documental: tipos essenciais do catálogo cobertos por documento vigente e não vencido
     (specs/21 §9.1). Verde a partir de 100%, âmbar acima de 60%, vermelho abaixo. --}}
@php
    $color = $percent >= 100 ? 'bg-green-600' : ($percent >= 60 ? 'bg-amber-500' : 'bg-red-600');
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 min-w-[7rem]']) }}>
    <div class="flex-1 h-2 rounded-full bg-hairline overflow-hidden">
        <div class="h-full {{ $color }}" style="width: {{ min(100, $percent) }}%"></div>
    </div>
    <span class="text-xs text-steel whitespace-nowrap">{{ $ok }}/{{ $total }}</span>
</div>
