<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Configurar iPhone</h2></x-slot>

    <x-page-header title="Configurar iPhone" subtitle="Habilite compartilhar orçamentos e NFs direto do WhatsApp pelo Atalho da Apple." icon="fa-mobile-screen-button">
        <x-slot name="actions">
            <a href="{{ route('captures.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Captura rápida
            </a>
        </x-slot>
    </x-page-header>

    <div class="max-w-2xl space-y-6">
        {{-- Token --}}
        <div class="bg-white border border-hairline rounded-xl p-6">
            <h3 class="font-semibold text-brand-ink mb-1"><i class="fa-solid fa-key text-brand-orange mr-2"></i>Seu token de captura</h3>
            <p class="text-xs text-steel mb-4">O Atalho usa este token para enviar arquivos ao upMusic sem você precisar fazer login toda vez. Ele dá acesso apenas para criar capturas — nada mais.</p>

            @if (session('captureToken'))
                <div class="rounded-lg border border-brand-orange bg-brand-orange/5 p-4 mb-4">
                    <p class="text-xs font-semibold text-brand-ink mb-2"><i class="fa-solid fa-triangle-exclamation text-brand-orange mr-1"></i>Copie agora — ele só aparece uma vez:</p>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly id="capture-token-value" value="{{ session('captureToken') }}" class="flex-1 border-gray-300 rounded-md text-xs font-mono bg-white" onclick="this.select()">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('capture-token-value').value); window.upAlerts.notifySuccess('Token copiado.')" class="shrink-0 rounded-md border border-hairline px-3 py-2 text-xs font-medium text-brand-ink hover:bg-surface">
                            <i class="fa-solid fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
            @elseif ($hasToken)
                <p class="text-sm text-steel mb-4"><i class="fa-solid fa-circle-check text-green-600 mr-1"></i>Você já tem um token ativo. Gere um novo se trocou de aparelho ou perdeu o anterior.</p>
            @else
                <p class="text-sm text-steel mb-4"><i class="fa-solid fa-circle-xmark text-steel mr-1"></i>Nenhum token gerado ainda.</p>
            @endif

            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('captures.token.store') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                        <i class="fa-solid fa-arrows-rotate"></i> {{ $hasToken ? 'Gerar novo token' : 'Gerar token' }}
                    </button>
                </form>
                @if ($hasToken)
                    <form method="POST" action="{{ route('captures.token.destroy') }}" data-confirm="Revogar o token? O Atalho no iPhone vai parar de funcionar.">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-ban"></i> Revogar
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Instruções --}}
        <div class="bg-white border border-hairline rounded-xl p-6">
            <h3 class="font-semibold text-brand-ink mb-4"><i class="fa-solid fa-list-ol text-brand-orange mr-2"></i>Como montar o Atalho</h3>

            <div class="rounded-lg border border-hairline p-3 mb-4 text-xs">
                <p class="text-steel mb-1">URL do endpoint (usar em "Obter Conteúdo do URL"):</p>
                <code class="block break-all bg-surface rounded px-2 py-1 text-brand-ink">{{ $receiveUrl }}</code>
            </div>

            <ol class="space-y-3 text-sm text-brand-ink list-decimal list-inside">
                <li>Abra o app <strong>Atalhos</strong> no iPhone → toque em <strong>+</strong> (Novo Atalho).</li>
                <li>Adicione primeiro a ação <strong>"Obter Conteúdo do URL"</strong> (antes de mais nada — um atalho vazio não libera as opções do próximo passo).</li>
                <li>Configure essa ação: URL = endereço acima; Método = <strong>POST</strong>; Cabeçalhos → <code>Authorization</code> = <code>Bearer &lt;seu token&gt;</code> e <code>Accept</code> = <code>application/json</code> (importante: sem isso, um erro de token vem como página de login em vez de mensagem clara); Corpo = <strong>Formulário</strong>, campo tipo Arquivo, chave <code>arquivos[]</code>, valor = <strong>Entrada do Atalho</strong>.</li>
                <li>Adicione <strong>"Obter Valor do Dicionário"</strong> → chave <code>confirm_url</code>.</li>
                <li>Adicione <strong>"Abrir URLs"</strong> → valor = <code>confirm_url</code>.</li>
                <li>Agora toque na <strong>seta ao lado do nome do atalho</strong>, no topo → <strong>Detalhes</strong> → ligue <strong>"Mostrar na Folha de Partilha"</strong> → em "Tipos" deixe só Arquivos e Imagens.</li>
                <li>Renomeie para "Enviar ao upMusic" e salve.</li>
                <li>Teste: no WhatsApp, abra um PDF → Compartilhar → escolha "Enviar ao upMusic".</li>
            </ol>

            <p class="text-xs text-steel mt-4">Se o iOS pedir, ative em Ajustes → Atalhos → "Permitir Atalhos Não Confiáveis" na primeira vez.</p>
        </div>
    </div>
</x-app-layout>
