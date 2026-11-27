@php use App\Domain\Enums\AccessModule; @endphp
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
        // Cada item aparece só com o módulo ligado no perfil de acesso (specs/28 §7.3); Admin vê
        // tudo. Uma seção some quando nenhum item dela aparece.
        $authUser = auth()->user();
        $isAdmin = $authUser->isAdmin();
        $can = fn (AccessModule $module) => $authUser->hasModule($module);

        // Quadros no menu: mesma régua de User::canAccessBoard() (Board::visibleTo). Ser responsável
        // por um card não coloca o quadro aqui (é leitura avulsa, ver BoardPolicy::view).
        $menuBoards = \App\Models\Board::query()
            ->visibleTo($authUser)
            ->orderBy('position')->orderBy('name')
            ->get(['id', 'name', 'icon']);

        // Quadro aberto agora: `boards.show` e o link direto do card (specs/18) compartilham o
        // parâmetro, então os dois acendem o submenu certo.
        $currentBoard = request()->route('board');
        $currentBoardId = $currentBoard instanceof \App\Models\Board ? $currentBoard->id : $currentBoard;

        $showCadastros = collect([
            AccessModule::Setores, AccessModule::Empresas, AccessModule::CostCenters, AccessModule::BankAccounts,
            AccessModule::FixedCosts, AccessModule::Fornecedores, AccessModule::FornecedorCategorias,
            AccessModule::Events, AccessModule::Templates,
        ])->contains($can);
        $showFinanceiro = collect([AccessModule::Finance, AccessModule::Prices, AccessModule::FinanceSettings])->contains($can);
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
            @if ($can(AccessModule::Captures))
                <x-nav-item route="captures.index" pattern="captures.*" icon="fa-bolt" label="Captura rápida" />
            @endif
        </div>

        @if ($showCadastros)
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Cadastros</p>
                @if ($can(AccessModule::Setores))
                    <x-nav-item route="setores.index" pattern="setores.*" icon="fa-sitemap" label="Setores" />
                @endif
                @if ($can(AccessModule::Empresas))
                    <x-nav-item route="empresas.index" pattern="empresas.*" icon="fa-building" label="Empresas" />
                @endif
                @if ($can(AccessModule::CostCenters))
                    <x-nav-item route="cost-centers.index" pattern="cost-centers.*" icon="fa-diagram-project" label="Centros de custo" />
                @endif
                @if ($can(AccessModule::BankAccounts))
                    <x-nav-item route="bank-accounts.index" pattern="bank-accounts.*" icon="fa-building-columns" label="Contas bancárias" />
                @endif
                @if ($can(AccessModule::FixedCosts))
                    <x-nav-item route="fixed-costs.index" pattern="fixed-costs.*" icon="fa-arrows-rotate" label="Custos fixos" />
                @endif
                @if ($can(AccessModule::Fornecedores))
                    <x-nav-item route="fornecedores.index" pattern="fornecedores.*" icon="fa-truck-field" label="Fornecedores" />
                @endif
                @if ($can(AccessModule::FornecedorCategorias))
                    {{-- Submenu de Fornecedores: gestão de categorias. --}}
                    <div class="{{ $can(AccessModule::Fornecedores) ? 'pl-4' : '' }}">
                        <x-nav-item route="fornecedor-categorias.index" pattern="fornecedor-categorias.*" icon="fa-tags"
                            label="Categorias" />
                    </div>
                @endif
                @if ($can(AccessModule::Events))
                    <x-nav-item route="eventos.index" pattern="eventos.*" icon="fa-calendar-days" label="Eventos" />
                @endif
                @if ($can(AccessModule::Templates))
                    <x-nav-item route="templates.index" pattern="templates.*" icon="fa-clone" label="Templates de Cards" />
                @endif
            </div>
        @endif

        @if ($showFinanceiro)
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Financeiro</p>
                {{-- Financeiro do Evento (specs/23): substitui o "Planejamento" (specs/09), que segue
                     acessível por URL só para consulta do histórico. --}}
                @if ($can(AccessModule::Finance))
                    <x-nav-item route="finance.index" pattern="finance.*" except="finance.settings.*"
                        icon="fa-file-invoice-dollar" label="Financeiro dos Eventos" />
                @endif
                @if ($can(AccessModule::Prices))
                    <x-nav-item route="prices.categorias.index" pattern="prices.*" icon="fa-tags" label="Banco de Preços" />
                @endif
                @if ($can(AccessModule::FinanceSettings))
                    <x-nav-item route="finance.settings.index" pattern="finance.settings.*" icon="fa-sliders"
                        label="Config. Financeiro" />
                @endif
            </div>
        @endif

        {{-- Gestão de Locações (specs/24): departamento isolado dos quadros/eventos — inventário e
             pedidos de LED, mesa, telão, som... --}}
        @if ($can(AccessModule::Rentals))
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Locações</p>
                <x-nav-item route="rentals.board" pattern="rentals.board" icon="fa-table-columns" label="Painel" />
                <x-nav-item route="rentals.orders.index" pattern="rentals.orders.*" icon="fa-file-invoice-dollar" label="Pedidos" />
                <x-nav-item route="rentals.items.index" pattern="rentals.items.*" icon="fa-boxes-stacked" label="Itens" />
            </div>
        @endif

        {{-- Licitações (specs/21). O badge conta documentos vencidos + vencendo e vem do mesmo cache
             do painel (TTL curto, invalidado ao mexer no acervo). --}}
        @if ($can(AccessModule::Bids))
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

        {{-- Administração: só o Administrador (specs/28 §2). --}}
        @if ($isAdmin)
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Administração</p>
                <x-nav-item route="users.index" pattern="users.*" icon="fa-users" label="Usuários" />
                <x-nav-item route="access-profiles.index" pattern="access-profiles.*" icon="fa-user-shield" label="Perfis de acesso" />
            </div>
        @endif
    </nav>

    {{-- Rodapé --}}
    <div class="px-5 py-4 border-t border-white/10 text-[11px] text-white/40">
        UpMusic &middot; v{{ config('app.version') }}
    </div>
</aside>
