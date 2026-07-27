{{-- Envio de edital (specs/21 §9.5). A análise roda na própria requisição — sem fila, sem worker. --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Nova análise de edital</h2></x-slot>

    <x-page-header title="Nova análise de edital" icon="fa-file-contract"
        subtitle="Envie o PDF/imagem do edital ou cole o texto. A IA extrai os requisitos e o sistema cruza com o acervo." />

    <div x-data="bidNotice()" class="max-w-3xl">
        <form action="{{ route('bid.notices.store') }}" method="POST" enctype="multipart/form-data"
              @submit="submit($event)" class="bg-white border border-hairline rounded-xl p-6 space-y-5">
            @csrf

            {{-- Escolha da fonte --}}
            <div class="flex items-center gap-1 border-b border-hairline pb-4">
                <button type="button" @click="mode = 'file'"
                        :class="mode === 'file' ? 'bg-brand-ink text-white' : 'border border-hairline text-brand-ink hover:bg-surface'"
                        class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors">
                    <i class="fa-solid fa-file-pdf"></i> Arquivo (PDF/imagem)
                </button>
                <button type="button" @click="mode = 'text'"
                        :class="mode === 'text' ? 'bg-brand-ink text-white' : 'border border-hairline text-brand-ink hover:bg-surface'"
                        class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors">
                    <i class="fa-solid fa-clipboard"></i> Colar texto
                </button>
            </div>

            {{-- Arquivo --}}
            <div x-show="mode === 'file'">
                <label class="flex flex-col items-center justify-center w-full px-6 py-10 border-2 border-dashed border-hairline rounded-lg cursor-pointer bg-surface hover:border-brand-orange transition-colors">
                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-steel"></i>
                    <span class="mt-3 text-sm text-steel">
                        <span class="font-medium text-brand-ink">Clique para enviar</span> ou arraste o edital
                    </span>
                    <span class="mt-1 text-xs text-steel/70">
                        PDF, JPG ou PNG — até {{ (int) (config('licitacoes.notice_max_kb') / 1024) }} MB
                    </span>
                    <span x-show="fileName" x-text="fileName" class="mt-2 text-xs font-medium text-brand-orange-deep"></span>
                    <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="onFileChange($event)">
                </label>
                <x-input-error :messages="$errors->get('arquivo')" class="mt-1" />
                <p class="mt-2 text-xs text-steel">
                    O arquivo vai inteiro para o modelo — inclusive tabelas e páginas escaneadas, onde a
                    habilitação normalmente está.
                </p>
            </div>

            {{-- Texto colado --}}
            <div x-show="mode === 'text'" x-cloak>
                <x-input-label for="raw_text" value="Texto do edital" />
                <textarea id="raw_text" name="raw_text" rows="12"
                          maxlength="{{ config('licitacoes.notice_text_max') }}"
                          placeholder="Cole aqui o texto do edital (ou pelo menos as seções de habilitação e qualificação)."
                          class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('raw_text') }}</textarea>
                <x-input-error :messages="$errors->get('raw_text')" class="mt-1" />
                <p class="mt-1 text-xs text-steel">
                    Mínimo de {{ config('licitacoes.notice_text_min') }} caracteres, máximo de
                    {{ number_format(config('licitacoes.notice_text_max'), 0, ',', '.') }}.
                </p>
            </div>

            <div>
                <x-input-label for="title" value="Título (opcional)" />
                <x-text-input id="title" name="title" class="mt-1" :value="old('title')"
                              placeholder="Se ficar em branco, a IA sugere um título a partir do edital" />
            </div>

            {{-- Empresas consideradas --}}
            <div class="border-t border-hairline pt-5">
                <x-input-label value="Empresas consideradas" />
                <p class="text-xs text-steel mb-2">
                    Sem seleção, todas as empresas ativas são avaliadas.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($companies as $company)
                        <label class="flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm hover:bg-surface cursor-pointer">
                            <input type="checkbox" name="companies[]" value="{{ $company->id }}"
                                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                            <span class="text-brand-ink truncate">{{ $company->display_name }}</span>
                            <span class="ml-auto text-xs text-steel">{{ $company->size->shortLabel() }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($companies->isEmpty())
                    <p class="text-sm text-red-600">
                        Nenhuma empresa ativa cadastrada —
                        <a href="{{ route('bid.companies.create') }}" class="underline">cadastre uma empresa</a> antes de analisar editais.
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-hairline pt-5">
                <p class="text-xs text-steel">
                    <i class="fa-solid fa-circle-info"></i>
                    A leitura acontece agora, nesta aba. Pode levar até 2 minutos em editais longos.
                </p>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('bid.notices.index') }}"
                       class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
                    <button type="submit" x-bind:disabled="submitting || {{ $companies->isEmpty() ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors disabled:opacity-50">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Analisar edital
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
