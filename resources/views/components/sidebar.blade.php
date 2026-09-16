{{-- Sidebar de navegação (SaaS). Fundo escuro + logo branca. Ver specs/02 e 06. --}}
<aside x-cloak
    class="fixed inset-y-0 left-0 z-40 w-64 bg-brand-ink flex flex-col transform transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

    {{-- Cabeçalho / logo --}}
    <div class="flex items-center justify-between h-16 px-5 border-b border-white/10">
        <a href="{{ route('dashboard') }}">
            <x-application-logo variant="branca" class="h-7" />
        </a>
        <button type="button" @click="sidebarOpen = false" class="lg:hidden text-white/60 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    {{-- Navegação --}}
    @php
        $isManager = auth()->user()->isAdmin() || auth()->user()->isCoordenador();
        $isAdmin = auth()->user()->isAdmin();
        // Coordenador restrito por evento (specs/20): esconde os cadastros que ele não pode acessar.
        $isEventScoped = auth()->user()->isEventScoped();

        // Quadros no menu: mesma régua do BoardController::index — admin/coordenador veem todos,
        // usuário comum só os que tem vínculo em `user_board`. Ser responsável por um card não
        // coloca o quadro aqui (é leitura avulsa, ver BoardPolicy::view).
        $menuBoards = \App\Models\Board::query()
            ->when(
                ! $isManager,
                fn ($q) => $q->whereHas('users', fn ($u) => $u->whereKey(auth()->id()))
            )
            ->orderBy('position')->orderBy('name')
            ->get(['id', 'name', 'icon']);

        // Quadro aberto agora: `boards.show` e o link direto do card (specs/18) compartilham o
        // parâmetro, então os dois acendem o submenu certo.
        $currentBoard = request()->route('board');
        $currentBoardId = $currentBoard instanceof \App\Models\Board ? $currentBoard->id : $currentBoard;
    @endphp
    <nav class="sidebar-nav flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <div class="space-y-1">
            <x-nav-item route="dashboard" pattern="dashboard" icon="fa-gauge-high" label="Painel" />
        </div>

        <div class="space-y-1">
            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Quadros</p>
            {{-- `except`: sem isso o item da lista acenderia junto com o submenu do quadro aberto,
                 já que `boards.*` casa com `boards.show`. --}}
            <x-nav-item route="boards.index" pattern="boards.*" except="boards.show*"
                icon="fa-table-columns" label="Quadros / Processos" />
            @foreach ($menuBoards as $menuBoard)
                <div class="pl-4">
                    <x-nav-item route="boards.show" :params="[$menuBoard]" :active="$currentBoardId == $menuBoard->id"
                        :icon="$menuBoard->icon ?: 'fa-table-columns'" :label="$menuBoard->name" />
                </div>
            @endforeach
            <x-nav-item route="cards.index" pattern="cards.index" icon="fa-layer-group" label="Todos os Cards" />
            @if ($isAdmin)
                <x-nav-item route="captures.index" pattern="captures.*" icon="fa-bolt" label="Captura rápida" />
            @endif
        </div>

        {{-- Fornecedores é liberado a qualquer usuário (todos podem criar/editar/excluir) — diferente
             de Setores/Empresas/Eventos/Templates, que continuam restritos a Admin/Coordenador, e do
             coordenador restrito por evento (specs/20), que não vê os demais cadastros. --}}
        <div class="space-y-1">
            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Cadastros</p>
            @if ($isManager && ! $isEventScoped)
                <x-nav-item route="setores.index" pattern="setores.*" icon="fa-sitemap" label="Setores" />
                <x-nav-item route="empresas.index" pattern="empresas.*" icon="fa-building" label="Empresas" />
            @endif
            <x-nav-item route="fornecedores.index" pattern="fornecedores.*" icon="fa-truck-field" label="Fornecedores" />
            @if ($isManager && ! $isEventScoped)
                {{-- Submenu de Fornecedores: gestão de categorias (restrito a Admin/Coordenador, specs/20). --}}
                <div class="pl-4">
                    <x-nav-item route="fornecedor-categorias.index" pattern="fornecedor-categorias.*" icon="fa-tags"
                        label="Categorias" />
                </div>
            @endif
            @if ($isManager)
                <x-nav-item route="eventos.index" pattern="eventos.*" icon="fa-calendar-days" label="Eventos" />
                @unless ($isEventScoped)
                    <x-nav-item route="templates.index" pattern="templates.*" icon="fa-clone" label="Templates de Cards" />
                @endunless
            @endif
        </div>


        <div class="space-y-1">
            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Financeiro</p>
            {{-- Financeiro do Evento (specs/23): substitui o "Planejamento" (specs/09), que segue
                 acessível por URL só para consulta do histórico. --}}
            @if ($isManager)
                <x-nav-item route="finance.index" pattern="finance.*" except="finance.settings.*"
                    icon="fa-file-invoice-dollar" label="Financeiro dos Eventos" />
            @endif
            <x-nav-item route="prices.categorias.index" pattern="prices.*" icon="fa-tags" label="Banco de Preços" />
            @if ($isAdmin)
                <x-nav-item route="finance.settings.index" pattern="finance.settings.*" icon="fa-sliders"
                    label="Config. Financeiro" />
            @endif
        </div>

        {{-- Licitações (specs/21): módulo exclusivo do Admin. O badge conta documentos vencidos +
             vencendo e vem do mesmo cache do painel (TTL curto, invalidado ao mexer no acervo). --}}
        @if ($isAdmin)
            @php($bidPending = app(\App\Services\Bid\BidDashboardService::class)->pendingCount())
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Licitações</p>
                <x-nav-item route="bid.dashboard" pattern="bid.dashboard" icon="fa-gavel" label="Painel de Licitações" />
                <x-nav-item route="bid.companies.index" pattern="bid.companies.*" icon="fa-building-flag"
                    label="Empresas" :badge="$bidPending ?: null" />
                <x-nav-item route="bid.notices.index" pattern="bid.notices.*" icon="fa-file-contract"
                    label="Análise de Editais" />
                <x-nav-item route="bid.reports.index" pattern="bid.reports.*" icon="fa-chart-column" label="Relatórios" />
                <x-nav-item route="bid.settings.index" pattern="bid.settings.*" icon="fa-sliders" label="Configurações" />
            </div>
        @endif

        @if ($isManager)
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Administração</p>
                <x-nav-item route="users.index" pattern="users.*" icon="fa-users" label="Usuários" />
            </div>
        @endif
    </nav>

    {{-- Rodapé --}}
    <div class="px-5 py-4 border-t border-white/10 text-[11px] text-white/40">
        UpMusic &middot; v{{ config('app.version') }}
    </div>
</aside>
