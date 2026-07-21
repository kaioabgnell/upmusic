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
            <h3 class="font-semibold text-brand-ink mb-1"><i class="fa-solid fa-list-ol text-brand-orange mr-2"></i>Como montar o Atalho (iPhone / iOS 26)</h3>
            <p class="text-xs text-steel mb-4">O Atalho tem <strong>uma única ação</strong>: envia o arquivo compartilhado para o upMusic. Depois de enviar, o arquivo aparece na <strong>Caixa de Entrada</strong> (Captura rápida) para você confirmar e criar o card no app.</p>

            <div class="rounded-lg border border-hairline p-3 mb-4 text-xs">
                <p class="text-steel mb-1">URL do endpoint (você vai colar no passo 3):</p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 break-all bg-surface rounded px-2 py-1 text-brand-ink">{{ $receiveUrl }}</code>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $receiveUrl }}'); window.upAlerts.notifySuccess('URL copiada.')" class="shrink-0 rounded-md border border-hairline px-2 py-1 text-brand-ink hover:bg-surface">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
            </div>

            <ol class="space-y-3 text-sm text-brand-ink list-decimal list-outside pl-5 marker:text-steel marker:font-semibold">
                <li>Abra o app <strong>Atalhos</strong> → toque em <strong>+</strong> (Novo Atalho).</li>
                <li>Em <strong>"Buscar Ações"</strong>, procure e adicione <strong>"Obter Conteúdo do URL"</strong>.</li>
                <li>
                    Toque na palavra <strong>URL</strong> dentro da ação e cole o endereço acima. Depois toque em
                    <strong>"Mostrar Mais"</strong> (a setinha na ação) e preencha:
                    <ul class="mt-2 space-y-1.5 list-disc list-outside pl-5 text-steel">
                        <li><strong>Método:</strong> <span class="text-brand-ink">POST</span></li>
                        <li>
                            <strong>Cabeçalhos</strong> (toque em "Adicionar novo cabeçalho" para cada um):
                            <div class="mt-1 space-y-1">
                                <div class="rounded bg-surface px-2 py-1"><span class="text-brand-ink">Chave:</span> <code>Authorization</code> &nbsp;·&nbsp; <span class="text-brand-ink">Texto:</span> <code>Bearer SEU_TOKEN</code></div>
                                <div class="rounded bg-surface px-2 py-1"><span class="text-brand-ink">Chave:</span> <code>Accept</code> &nbsp;·&nbsp; <span class="text-brand-ink">Texto:</span> <code>application/json</code></div>
                            </div>
                            <span class="block mt-1 text-[11px]">Substitua <code>SEU_TOKEN</code> pelo token que você gerou acima (cole o valor inteiro depois de <code>Bearer&nbsp;</code>). Se sobrar uma linha de cabeçalho vazia, apague-a no botão vermelho.</span>
                        </li>
                        <li><strong>Pedir Corpo:</strong> <span class="text-brand-ink">Formulário</span></li>
                        <li>
                            Toque em <strong>"Adicionar novo campo"</strong> → escolha o tipo <strong>Ficheiro</strong> (Arquivo) →
                            em <strong>Chave</strong> escreva <code>arquivos[]</code> → toque no valor e escolha a variável
                            <strong>"Entrada do Atalho"</strong> (é o arquivo que veio do WhatsApp).
                        </li>
                    </ul>
                </li>
                <li>
                    <em>(Opcional, recomendado)</em> Adicione a ação <strong>"Mostrar Notificação"</strong> com um texto fixo,
                    ex.: <span class="text-steel">"Enviado ao upMusic"</span> — assim você vê a confirmação de que deu certo.
                </li>
                <li>
                    Ative o compartilhamento: toque na <strong>seta (⌄) ao lado do nome do atalho</strong> no topo →
                    <strong>Detalhes</strong> → ligue <strong>"Mostrar na Folha de Partilha"</strong>. Isso adiciona no
                    topo do atalho um bloco <em>"Receber … de Compartilhamento"</em> — toque nos tipos e deixe marcado
                    apenas <strong>Imagens</strong> e <strong>Ficheiros</strong> (o resto pode desmarcar).
                </li>
                <li>Toque no nome no topo → <strong>Renomear</strong> → "Enviar ao upMusic". Salve (toque em Concluído).</li>
                <li>
                    <strong>Testar:</strong> no WhatsApp, abra um PDF → <strong>Compartilhar</strong> → role a lista e toque
                    em <strong>"Enviar ao upMusic"</strong>.
                </li>
            </ol>

            <div class="mt-4 rounded-lg bg-brand-orange/5 border border-brand-orange/30 p-3 text-xs text-brand-ink">
                <i class="fa-solid fa-circle-info text-brand-orange mr-1"></i>
                Depois de compartilhar, o arquivo <strong>não vira card sozinho</strong>: ele fica na
                <a href="{{ route('captures.index') }}" class="text-brand-orange-deep hover:underline font-medium">Caixa de Entrada</a>
                aguardando você abrir o app, escolher o quadro e confirmar. Dá para enviar vários seguidos e confirmar todos depois.
            </div>
        </div>
    </div>
</x-app-layout>
