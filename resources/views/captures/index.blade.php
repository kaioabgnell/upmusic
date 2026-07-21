<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Captura rápida</h2></x-slot>

    <x-page-header title="Captura rápida" subtitle="Orçamentos e notas fiscais aguardando virar card." icon="fa-bolt">
        <x-slot name="actions">
            <a href="{{ route('captures.index') }}#enviar" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Nova captura
            </a>
        </x-slot>
    </x-page-header>

    @if ($captures->isEmpty())
        <div class="bg-white border border-hairline rounded-xl mb-6">
            <x-empty-state icon="fa-inbox" title="Nenhuma captura pendente" message="Envie um PDF ou imagem de orçamento/NF pelo formulário abaixo para começar.">
                <x-slot name="action">
                    <a href="#enviar" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                        <i class="fa-solid fa-plus"></i> Enviar arquivo
                    </a>
                </x-slot>
            </x-empty-state>
        </div>
    @else
        <div class="bg-white border border-hairline rounded-xl overflow-hidden mb-6">
            <div class="divide-y divide-hairline">
                @foreach ($captures as $capture)
                    <div class="flex items-center gap-3 p-4">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-surface text-steel shrink-0">
                            <i class="fa-solid {{ str_contains((string) $capture->mime, 'image') ? 'fa-image' : 'fa-file-pdf' }}"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-brand-ink truncate">{{ $capture->original_name }}</p>
                            <p class="text-xs text-steel">{{ $capture->created_at->diffForHumans() }} &middot; {{ $capture->source->label() }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('captures.show', $capture) }}" class="inline-flex items-center gap-1.5 rounded-md border border-hairline px-3 py-1.5 text-xs font-medium text-brand-ink hover:bg-surface">
                                <i class="fa-solid fa-file-circle-plus"></i> Criar card
                            </a>
                            <form method="POST" action="{{ route('captures.destroy', $capture) }}" data-confirm="Descartar esta captura?">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Descartar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-4 py-3 border-t border-hairline bg-white">{{ $captures->links() }}</div>
        </div>
    @endif

    <div id="enviar" class="bg-white border border-hairline rounded-xl p-6">
        <h3 class="font-semibold text-brand-ink mb-1"><i class="fa-solid fa-cloud-arrow-up text-brand-orange mr-2"></i>Enviar orçamento ou nota fiscal</h3>
        <p class="text-xs text-steel mb-4">Selecione um ou mais arquivos (PDF ou imagem). Cada um vira uma captura pendente acima.</p>
        @include('captures.partials.upload-form')
    </div>

    <p class="text-xs text-steel mt-4">
        <i class="fa-solid fa-mobile-screen-button mr-1"></i>
        Prefere compartilhar direto do WhatsApp? No Android, instale o app (banner no topo). No iPhone,
        <a href="{{ route('captures.ios.setup') }}" class="text-brand-orange-deep hover:underline">configure o Atalho aqui</a>.
    </p>
</x-app-layout>
