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
    @php $isManager = auth()->user()->isAdmin() || auth()->user()->isCoordenador(); @endphp
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <div class="space-y-1">
            <x-nav-item route="dashboard" pattern="dashboard" icon="fa-gauge-high" label="Painel" />
        </div>

        <div class="space-y-1">
            <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Quadros</p>
            <x-nav-item route="boards.index" pattern="boards.*" icon="fa-table-columns" label="Quadros / Processos" />
            <x-nav-item route="cards.index" pattern="cards.index" icon="fa-layer-group" label="Todos os Cards" />
            <x-nav-item route="captures.index" pattern="captures.*" icon="fa-bolt" label="Captura rápida" />
        </div>

        @if ($isManager)
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Cadastros</p>
                <x-nav-item route="setores.index" pattern="setores.*" icon="fa-sitemap" label="Setores" />
                <x-nav-item route="empresas.index" pattern="empresas.*" icon="fa-building" label="Empresas" />
                <x-nav-item route="fornecedores.index" pattern="fornecedores.*" icon="fa-truck-field" label="Fornecedores" />
                <x-nav-item route="eventos.index" pattern="eventos.*" icon="fa-calendar-days" label="Eventos" />
                @if ($isManager)
                    <x-nav-item route="templates.index" pattern="templates.*" icon="fa-clone" label="Templates de Cards" />
                @endif
            </div>
        @endif

        
            <div class="space-y-1">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Financeiro</p>
                @if ($isManager)
                    @if (false)
                        <x-nav-item route="plans.index" pattern="plans.*" icon="fa-chart-line" label="Planejamento" />
                    @endif
                @endif
                <x-nav-item route="prices.categorias.index" pattern="prices.*" icon="fa-tags" label="Banco de Preços" />
            </div>
        

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
