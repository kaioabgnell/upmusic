@props([
    'name',
    'accept' => null,
    'hint' => null,
])

{{-- Upload de arquivo estilizado (para anexos e notas fiscais). --}}
<label class="flex flex-col items-center justify-center w-full px-6 py-8 border-2 border-dashed border-hairline rounded-lg cursor-pointer bg-surface hover:border-brand-orange transition-colors"
       x-data="{ fileName: '' }">
    <i class="fa-solid fa-cloud-arrow-up text-2xl text-steel"></i>
    <span class="mt-2 text-sm text-steel">
        <span class="font-medium text-brand-ink">Clique para enviar</span> ou arraste o arquivo
    </span>
    @if($hint)<span class="mt-1 text-xs text-steel/70">{{ $hint }}</span>@endif
    <span x-show="fileName" x-text="fileName" class="mt-2 text-xs font-medium text-brand-orange-deep"></span>
    <input type="file"
           name="{{ $name }}"
           @if($accept) accept="{{ $accept }}" @endif
           @change="fileName = $event.target.files[0]?.name ?? ''"
           {{ $attributes->merge(['class' => 'hidden']) }}>
</label>
