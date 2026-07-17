@props([
    'head' => null, // slot com <th>...</th>
])

{{-- Card com tabela responsiva. Uso:
     <x-data-table>
        <x-slot name="head"><th ...>Nome</th> ...</x-slot>
        <tr>...</tr>
     </x-data-table>
--}}
<div {{ $attributes->merge(['class' => 'bg-white border border-hairline rounded-xl overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-hairline text-sm">
            @isset($head)
                <thead class="bg-surface">
                    <tr class="text-left text-steel">
                        {{ $head }}
                    </tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-hairline">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="px-4 py-3 border-t border-hairline bg-white">
            {{ $footer }}
        </div>
    @endisset
</div>
