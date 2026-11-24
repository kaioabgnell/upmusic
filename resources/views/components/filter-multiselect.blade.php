@props([
    'name',                          // vira `name[]` nos checkboxes (GET simples, chega como array)
    'label',
    'options' => [],                 // [['id' => 1, 'label' => '…', 'search' => '…'?, 'muted' => bool?, 'title' => '…'?]]
    'selected' => [],
    'noneLabel' => null,             // opção "sem …" no topo (value "none"), quando existir
    'allLabel' => 'Todas',
    'unit' => 'selecionadas',
    'searchPlaceholder' => 'Pesquisar',
    'emptyText' => 'Nada encontrado.',
])

@php
    // Filtro de seleção múltipla com busca (Empresa, Centro de custo, Conta bancária). O resumo do
    // botão acompanha a seleção na hora; o envio continua sendo o GET do formulário.
    $items = collect($options)->map(fn ($o) => [
        'id' => (string) $o['id'],
        'label' => $o['label'],
        'search' => mb_strtolower($o['search'] ?? $o['label']),
        'muted' => (bool) ($o['muted'] ?? false),
        'title' => $o['title'] ?? null,
    ])->values();
    $labels = $items->pluck('label', 'id')->all();
    if ($noneLabel) {
        $labels['none'] = $noneLabel;
    }
    $inputId = 'filtro-'.$name;
@endphp

{{-- Largura vem de quem usa (ex.: class="w-full sm:w-44"). --}}
<div {{ $attributes }}
     x-data="{
        open: false,
        search: '',
        checked: {{ Illuminate\Support\Js::from(array_values(array_map('strval', (array) $selected))) }},
        labels: {{ Illuminate\Support\Js::from($labels) }},
        haystack: {{ Illuminate\Support\Js::from($items->pluck('search')) }},
        get summary() {
            if (this.checked.length === 0) return {{ Illuminate\Support\Js::from($allLabel) }};
            if (this.checked.length === 1) return this.labels[this.checked[0]] ?? '1';
            return `${this.checked.length} {{ $unit }}`;
        },
        get noMatch() { return this.search !== '' && this.haystack.every(n => !n.includes(this.search.toLowerCase())); },
        toggle() { this.open = !this.open; this.search = ''; if (this.open) this.$nextTick(() => this.$refs.search.focus()); },
     }"
     @click.outside="open = false" @keydown.escape.stop="open = false">
    <label for="{{ $inputId }}" class="text-xs text-steel">{{ $label }}</label>
    <div class="relative mt-1">
        <button type="button" id="{{ $inputId }}" @click="toggle()" :aria-expanded="open" aria-haspopup="true"
                class="w-full h-9 flex items-center justify-between gap-2 rounded-md border bg-white px-3 text-sm text-left transition-colors hover:border-brand-orange focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange"
                :class="checked.length ? 'border-brand-orange/60' : 'border-gray-300'">
            <span class="truncate" :class="checked.length ? 'text-brand-ink' : 'text-steel'" x-text="summary"></span>
            <i class="fa-solid fa-chevron-down text-[10px] text-steel shrink-0 transition-transform" :class="open && 'rotate-180'"></i>
        </button>

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             class="absolute left-0 z-30 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-2">
            <div class="relative mb-2">
                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                <input type="text" x-ref="search" x-model="search" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}"
                       class="w-full pl-7 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
            </div>
            <div class="max-h-56 overflow-y-auto space-y-0.5">
                @if ($noneLabel)
                    <label x-show="search === ''"
                           class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm cursor-pointer border-b border-hairline mb-1">
                        <input type="checkbox" name="{{ $name }}[]" value="none" x-model="checked"
                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        <span class="flex-1 truncate text-steel">{{ $noneLabel }}</span>
                    </label>
                @endif
                @foreach ($items as $item)
                    <label x-show="search === '' || {{ Illuminate\Support\Js::from($item['search']) }}.includes(search.toLowerCase())"
                           class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm cursor-pointer"
                           @if ($item['title']) title="{{ $item['title'] }}" @endif>
                        <input type="checkbox" name="{{ $name }}[]" value="{{ $item['id'] }}" x-model="checked"
                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        <span class="flex-1 truncate {{ $item['muted'] ? 'text-steel' : 'text-brand-ink' }}">{{ $item['label'] }}</span>
                    </label>
                @endforeach
                <p x-show="noMatch" class="text-xs text-steel px-2 py-1.5">{{ $emptyText }}</p>
            </div>
            <button type="button" x-show="checked.length" x-cloak @click="checked = []"
                    class="w-full text-left px-2 py-1.5 mt-1 text-xs text-red-600 hover:underline border-t border-hairline">Limpar seleção</button>
        </div>
    </div>
</div>
