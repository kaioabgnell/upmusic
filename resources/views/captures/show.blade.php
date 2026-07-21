@php $isImage = str_contains((string) $capture->mime, 'image'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Confirmar captura</h2></x-slot>

    <x-page-header title="Confirmar captura" subtitle="Defina o mínimo e crie o card." icon="fa-file-circle-plus">
        <x-slot name="actions">
            <a href="{{ route('captures.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Caixa de entrada
            </a>
        </x-slot>
    </x-page-header>

    <form method="POST" action="{{ route('captures.store', $capture) }}" class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl" x-data="{ showMore: false }">
        @csrf

        {{-- Prévia do arquivo --}}
        <div class="flex items-center gap-3 rounded-lg border border-hairline p-3">
            @if ($isImage)
                <img src="{{ route('captures.preview', $capture) }}" alt="{{ $capture->original_name }}" class="w-16 h-16 rounded-md object-cover shrink-0">
            @else
                <span class="inline-flex items-center justify-center w-16 h-16 rounded-md bg-surface text-steel shrink-0">
                    <i class="fa-solid fa-file-pdf text-2xl"></i>
                </span>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-medium text-brand-ink truncate">{{ $capture->original_name }}</p>
                <p class="text-xs text-steel">
                    <a href="{{ route('captures.preview', $capture) }}" target="_blank" class="text-brand-orange-deep hover:underline">
                        <i class="fa-solid fa-eye"></i> Abrir arquivo
                    </a>
                </p>
            </div>
        </div>

        {{-- Tipo --}}
        <div>
            <x-input-label value="Tipo" />
            <div class="mt-1 inline-flex items-center rounded-md border border-hairline p-0.5">
                @foreach (['orcamento' => 'Orçamento', 'nota_fiscal' => 'Nota fiscal'] as $value => $label)
                    <label class="inline-flex items-center gap-1.5 rounded px-3 h-8 text-sm font-medium cursor-pointer has-[:checked]:bg-brand-orange has-[:checked]:text-brand-ink text-steel">
                        <input type="radio" name="kind" value="{{ $value }}" class="sr-only" @checked(old('kind', 'orcamento') === $value)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('kind')" class="mt-1" />
        </div>

        {{-- Quadro --}}
        <div>
            <x-input-label for="board_id" value="Quadro" />
            <x-form.select id="board_id" name="board_id" class="mt-1">
                <option value="">— Selecione —</option>
                @foreach ($boards as $board)
                    <option value="{{ $board->id }}" @selected(old('board_id', $lastBoardId) == $board->id)>{{ $board->name }}</option>
                @endforeach
            </x-form.select>
            <x-input-error :messages="$errors->get('board_id')" class="mt-1" />
        </div>

        {{-- Opcionais recolhíveis --}}
        <div class="border-t border-hairline pt-4">
            <button type="button" @click="showMore = !showMore" class="text-sm font-medium text-brand-orange-deep hover:underline">
                <i class="fa-solid" :class="showMore ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                <span x-text="showMore ? 'Ocultar campos opcionais' : 'Preencher campos opcionais'"></span>
            </button>

            <div x-show="showMore" x-cloak class="mt-4 space-y-4">
                <div>
                    <x-input-label for="title" value="Título" />
                    <x-text-input id="title" name="title" :value="old('title', $capture->suggested_title)" class="mt-1" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="empresa_id" value="Empresa" />
                        <x-form.select id="empresa_id" name="empresa_id" class="mt-1">
                            <option value="">— Nenhuma —</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}" @selected(old('empresa_id') == $empresa->id)>{{ $empresa->corporate_name }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                    <div>
                        <x-input-label for="fornecedor_id" value="Fornecedor" />
                        <x-form.select id="fornecedor_id" name="fornecedor_id" class="mt-1">
                            <option value="">— Nenhum —</option>
                            @foreach ($fornecedores as $fornecedor)
                                <option value="{{ $fornecedor['id'] }}" @selected(old('fornecedor_id') == $fornecedor['id'])>{{ $fornecedor['name'] }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                    <div>
                        <x-input-label for="event_id" value="Evento" />
                        <x-form.select id="event_id" name="event_id" class="mt-1">
                            <option value="">— Nenhum —</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->name }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                    <div>
                        <x-input-label for="estimated_value" value="Valor" />
                        <div class="relative mt-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
                            <input type="text" inputmode="decimal" id="estimated_value" name="estimated_value" x-data
                                   x-mask:dynamic="$money($input, ',')" value="{{ old('estimated_value') }}" placeholder="0,00"
                                   class="w-full pl-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
            <a href="{{ route('captures.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-file-circle-plus"></i> Criar card
            </button>
        </div>
    </form>
</x-app-layout>
