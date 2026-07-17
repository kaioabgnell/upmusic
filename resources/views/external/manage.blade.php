@php $publicUrl = route('external.form.show', $form->token); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Formulário externo: {{ $board->name }}</h2></x-slot>

    <x-page-header title="Formulário externo" :subtitle="$board->name" icon="fa-share-nodes">
        <x-slot name="actions">
            <a href="{{ route('boards.show', $board) }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-arrow-left"></i> Voltar ao quadro</a>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Link + configuração --}}
        <div class="lg:col-span-2 space-y-6">
            <form method="POST" action="{{ route('external.forms.update', $board) }}" class="bg-white border border-hairline rounded-xl p-6 space-y-5">
                @csrf @method('PUT')
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-gear text-brand-orange mr-2"></i>Configuração</h3>
                <div>
                    <x-input-label for="title" value="Título exibido ao cliente" />
                    <x-text-input id="title" name="title" :value="old('title', $form->title)" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="target_column_id" value="Coluna de análise (onde o card chega)" />
                    <x-form.select id="target_column_id" name="target_column_id" class="mt-1">
                        <option value="">— Entrada padrão do quadro —</option>
                        @foreach ($board->columns as $col)
                            <option value="{{ $col->id }}" @selected($form->target_column_id == $col->id)>{{ $col->name }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div>
                    <x-input-label for="event_id" value="Evento" />
                    <x-form.select id="event_id" name="event_id" class="mt-1">
                        <option value="">— Nenhum —</option>
                        @foreach ($events as $ev)
                            <option value="{{ $ev->id }}" @selected(old('event_id', $form->event_id) == $ev->id)>{{ $ev->name }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <label class="flex items-center gap-2 text-sm text-brand-ink">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked($form->active) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"> Formulário ativo
                </label>
                <div class="flex items-center justify-between border-t border-hairline pt-5">
                    <button type="button" onclick="document.getElementById('regen-form').submit()" class="text-sm text-steel hover:text-brand-ink"><i class="fa-solid fa-rotate"></i> Gerar novo link</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
            <form id="regen-form" method="POST" action="{{ route('external.forms.regenerate', $board) }}" class="hidden" data-confirm="Gerar um novo link invalida o atual. Continuar?">
                @csrf
            </form>

            <div class="bg-white border border-hairline rounded-xl p-6" x-data="{ url: @js($publicUrl) }">
                <h3 class="font-semibold text-brand-ink mb-1"><i class="fa-solid fa-link text-brand-orange mr-2"></i>Link público</h3>
                <p class="text-xs text-steel mb-3">Envie este link aos clientes para preenchimento e anexo da nota fiscal.</p>
                <div class="flex gap-2">
                    <input type="text" readonly :value="url" class="flex-1 border-gray-300 bg-surface rounded-md text-sm text-steel">
                    <button type="button" @click="navigator.clipboard.writeText(url); window.upAlerts.notifySuccess('Link copiado!')"
                            class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-3 py-2 text-sm font-medium text-white hover:bg-black"><i class="fa-solid fa-copy"></i> Copiar</button>
                    <a :href="url" target="_blank" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-up-right-from-square"></i></a>
                </div>
                @unless ($form->active)
                    <p class="mt-3 text-xs text-red-600"><i class="fa-solid fa-triangle-exclamation"></i> O formulário está inativo — o link não aceita envios.</p>
                @endunless
            </div>
        </div>

        {{-- Submissions recentes --}}
        <div class="bg-white border border-hairline rounded-xl p-6">
            <h3 class="font-semibold text-brand-ink mb-3"><i class="fa-solid fa-inbox text-brand-orange mr-2"></i>Envios recentes</h3>
            @forelse ($form->submissions as $sub)
                <div class="py-2 border-b border-hairline last:border-0">
                    <p class="text-sm font-medium text-brand-ink">{{ $sub->name }}</p>
                    <p class="text-xs text-steel">{{ \App\Support\Br::formatCnpj($sub->cnpj) }} · R$ {{ number_format((float) $sub->value, 2, ',', '.') }}</p>
                    @if ($sub->payment_data)
                        <p class="text-xs text-steel truncate" title="{{ $sub->payment_data }}"><i class="fa-solid fa-money-check-dollar text-brand-orange-deep mr-1"></i>{{ $sub->payment_data }}</p>
                    @endif
                    <p class="text-xs text-steel">{{ $sub->created_at->format('d/m/Y H:i') }}
                        @if ($sub->card_id)<a href="{{ route('boards.show', $board) }}" class="text-brand-orange-deep hover:underline">· ver no quadro</a>@endif
                    </p>
                </div>
            @empty
                <p class="text-sm text-steel">Nenhum envio ainda.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
