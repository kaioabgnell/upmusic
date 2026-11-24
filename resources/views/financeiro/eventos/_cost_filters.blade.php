@php
    // Bloco de filtros da aba CUSTOS, compartilhado com o RELATÓRIO (a regra mora em
    // App\Services\Finance\CostItemFilter). Sem `action`: o GET volta para a própria tela.
    // Espera: $clearUrl, $categorias, $statuses, $fornecedores, $costCenters, $bankAccounts, $sources.
    //
    // Na barra ficam os quatro filtros do dia a dia (descrição, empresa, centro de custo, conta). Os
    // demais moram na "Busca avançada" (painel lateral) e, quando ativos, aparecem como etiquetas
    // removíveis logo abaixo da barra — filtro aplicado nunca fica escondido.
    $filtroAtivo = ! \App\Services\Finance\CostItemFilter::fromRequest(request())->isEmpty();

    $totalReal = filled(request('total_actual')) ? \App\Support\Br::money((string) request('total_actual')) : null;
    $avancados = array_values(array_filter([
        request('categoria_id') ? ['key' => 'categoria_id', 'text' => 'Item: '.($categorias->firstWhere('id', (int) request('categoria_id'))?->nome ?? '—')] : null,
        isset($statuses[request('status')]) ? ['key' => 'status', 'text' => 'Status: '.$statuses[request('status')]] : null,
        request('source_id') ? ['key' => 'source_id', 'text' => 'Grupo: '.($sources->firstWhere('id', (int) request('source_id'))?->name ?? '—')] : null,
        $totalReal !== null ? ['key' => 'total_actual', 'text' => 'Total realizado: '.\App\Support\Br::formatMoney($totalReal)] : null,
        request()->boolean('sem_comprovante') ? ['key' => 'sem_comprovante', 'text' => 'Sem comprovante'] : null,
        request()->boolean('falta_pagar') ? ['key' => 'falta_pagar', 'text' => 'Falta pagar'] : null,
        request()->boolean('pago_a_maior') ? ['key' => 'pago_a_maior', 'text' => 'Pago a maior'] : null,
    ]));

    $field = 'w-full h-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm';
@endphp

<form method="GET" class="mb-4"
      x-data="{
          advancedOpen: false,
          totalActual: {{ Illuminate\Support\Js::from((string) request('total_actual', '')) }},
          openAdvanced() {
              this.advancedOpen = true;
              this.$nextTick(() => this.$refs.advancedFirst.focus());
          },
          closeAdvanced() {
              this.advancedOpen = false;
              this.$nextTick(() => this.$refs.advancedTrigger.focus());
          },
          clearAdvanced() {
              this.$refs.advanced.querySelectorAll('select').forEach(el => el.value = '');
              this.$refs.advanced.querySelectorAll('input[type=checkbox]').forEach(el => el.checked = false);
              this.totalActual = '';
          },
      }"
      x-effect="document.body.classList.toggle('overflow-hidden', advancedOpen)"
      @keydown.escape.window="advancedOpen && closeAdvanced()">

    {{-- Barra: os quatro filtros principais + ações. Mesmo formato com ou sem filtro ativo ("Limpar"
         fica na linha de baixo). No celular, cada campo ocupa a largura toda. --}}
    <div class="flex flex-wrap items-end gap-2">
        <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[14rem]">
            <label for="filtro-search" class="text-xs text-steel">Buscar descrição</label>
            <div class="relative mt-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-steel pointer-events-none"></i>
                <input type="search" id="filtro-search" name="search" value="{{ request('search') }}" placeholder="Ex.: gerador, palco, segurança"
                       class="{{ $field }} pl-8">
            </div>
        </div>

        <x-filter-multiselect class="w-full sm:w-44" name="fornecedor_id" label="Empresa" :selected="request('fornecedor_id', [])"
            :options="$fornecedores->map(fn ($f) => ['id' => $f->id, 'label' => $f->name])"
            all-label="Todas" unit="selecionadas" search-placeholder="Pesquisar empresa" empty-text="Nenhuma empresa encontrada." />

        <x-filter-multiselect class="w-full sm:w-44" name="cost_center_id" label="Centro de custo" :selected="request('cost_center_id', [])"
            :options="collect($costCenters)->map(fn ($c) => ['id' => $c['id'], 'label' => $c['label'], 'muted' => ! $c['active']])"
            none-label="Sem centro de custo" all-label="Todos" unit="selecionados"
            search-placeholder="Pesquisar centro de custo" empty-text="Nenhum centro de custo encontrado." />

        {{-- Conta bancária (specs/26): de onde saiu o dinheiro — a linha entra se tiver pagamento da(s)
             conta(s); "Pagamento sem conta" acha os pagamentos ainda sem conta. --}}
        <x-filter-multiselect class="w-full sm:w-44" name="bank_account_id" label="Conta bancária" :selected="request('bank_account_id', [])"
            :options="collect($bankAccounts)->map(fn ($c) => ['id' => $c['id'], 'label' => $c['label'], 'search' => $c['label'].' '.$c['bank'], 'muted' => ! $c['active'], 'title' => $c['bank']])"
            none-label="Pagamento sem conta" all-label="Todas" unit="selecionadas"
            search-placeholder="Pesquisar conta bancária" empty-text="Nenhuma conta bancária encontrada." />

        <div class="flex w-full sm:w-auto items-center gap-2">
            <button type="button" x-ref="advancedTrigger" @click="openAdvanced()"
                    :aria-expanded="advancedOpen" aria-controls="busca-avancada"
                    class="relative flex-1 sm:flex-none h-9 inline-flex items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange active:scale-[0.98] {{ $avancados ? 'border-brand-orange text-brand-ink bg-brand-orange/5' : 'border-hairline text-brand-ink bg-white hover:bg-surface' }}">
                <i class="fa-solid fa-sliders text-xs"></i> Busca avançada
                @if ($avancados)
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-brand-orange text-brand-ink text-[10px] font-bold"
                          aria-label="{{ count($avancados) }} filtro(s) avançado(s) ativo(s)">{{ count($avancados) }}</span>
                @endif
            </button>
            <button type="submit"
                    class="flex-1 sm:flex-none h-9 inline-flex items-center justify-center gap-2 rounded-md bg-brand-ink px-4 text-sm font-medium text-white transition-colors hover:bg-black focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange focus-visible:ring-offset-1 active:scale-[0.98]">
                <i class="fa-solid fa-filter text-xs"></i> Filtrar
            </button>
        </div>
    </div>

    {{-- Filtros avançados ativos (visíveis mesmo com o painel fechado, cada um removível) e o
         "Limpar filtros" — fora da barra, para ela não mudar de formato quando há filtro. --}}
    @if ($filtroAtivo)
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            @if ($avancados)
                <span class="text-xs text-steel mr-0.5">Busca avançada:</span>
            @endif
            @foreach ($avancados as $chip)
                <a href="{{ request()->fullUrlWithoutQuery([$chip['key']]) }}" title="Remover este filtro"
                   class="group inline-flex items-center gap-1.5 rounded-full border border-hairline bg-white pl-2.5 pr-2 py-1 text-xs text-brand-ink transition-colors hover:border-red-300 hover:bg-red-50">
                    {{ $chip['text'] }}
                    <i class="fa-solid fa-xmark text-[10px] text-steel group-hover:text-red-600" aria-hidden="true"></i>
                    <span class="sr-only">Remover</span>
                </a>
            @endforeach
            @if ($avancados)
                <button type="button" @click="openAdvanced()" class="px-1 py-1 text-xs font-medium text-brand-orange-deep hover:underline">Editar</button>
                <span class="text-hairline" aria-hidden="true">|</span>
            @endif
            <a href="{{ $clearUrl }}" class="inline-flex items-center gap-1 px-1 py-1 text-xs font-medium text-steel hover:text-red-600">
                <i class="fa-solid fa-xmark text-[10px]" aria-hidden="true"></i> Limpar filtros
            </a>
        </div>
    @endif

    {{-- Painel lateral da busca avançada. Dentro do <form>: "Aplicar" envia junto os filtros da barra. --}}
    <div x-show="advancedOpen" x-cloak class="fixed inset-0 z-50 flex justify-end" x-transition.opacity>
        <div class="absolute inset-0 bg-black/40" @click="closeAdvanced()" aria-hidden="true"></div>

        <div id="busca-avancada" x-ref="advanced" role="dialog" aria-modal="true" aria-labelledby="busca-avancada-titulo"
             class="relative w-full max-w-md bg-white h-full shadow-xl flex flex-col"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">

            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-hairline shrink-0">
                <div>
                    <h2 id="busca-avancada-titulo" class="text-base font-semibold text-brand-ink">Busca avançada</h2>
                    <p class="text-xs text-steel mt-0.5">Somam-se aos filtros da barra.</p>
                </div>
                <button type="button" @click="closeAdvanced()" aria-label="Fechar busca avançada"
                        class="w-9 h-9 -mr-2 inline-flex items-center justify-center rounded-md text-steel hover:bg-surface hover:text-brand-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-5 space-y-5">
                <div>
                    <label for="filtro-categoria" class="text-sm font-medium text-brand-ink">Item</label>
                    <select id="filtro-categoria" name="categoria_id" x-ref="advancedFirst" class="mt-1 {{ $field }}">
                        <option value="">Todos</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}" @selected(request('categoria_id') == $categoria->id)>{{ $categoria->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filtro-status" class="text-sm font-medium text-brand-ink">Status</label>
                    <select id="filtro-status" name="status" class="mt-1 {{ $field }}">
                        <option value="">Todos</option>
                        @foreach ($statuses as $value => $statusLabel)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filtro-grupo" class="text-sm font-medium text-brand-ink">Grupo de pagamento</label>
                    <select id="filtro-grupo" name="source_id" class="mt-1 {{ $field }}">
                        <option value="">Todos</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected(request('source_id') == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-steel">Linhas com ao menos um pagamento deste grupo.</p>
                </div>

                <div>
                    <label for="filtro-total" class="text-sm font-medium text-brand-ink">Total realizado</label>
                    {{-- Mesma máscara dos valores do card/Financeiro (window.maskMoneyDigits, app.js). --}}
                    <div class="relative mt-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
                        <input type="text" id="filtro-total" name="total_actual" inputmode="decimal" :value="totalActual"
                               @input="totalActual = window.maskMoneyDigits($event.target.value)"
                               placeholder="0,00" class="{{ $field }} pl-9">
                    </div>
                    <p class="mt-1 text-xs text-steel">Valor exato do total realizado da linha.</p>
                </div>

                <fieldset>
                    <legend class="text-sm font-medium text-brand-ink">Situação da linha</legend>
                    <div class="mt-2 space-y-2">
                        @foreach ([
                            'sem_comprovante' => ['Sem comprovante', 'Tem valor realizado e nenhum comprovante anexado.'],
                            'falta_pagar' => ['Falta pagar', 'O realizado é maior que o total já pago.'],
                            'pago_a_maior' => ['Pago a maior', 'Os pagamentos passam do valor realizado.'],
                        ] as $key => [$optionLabel, $hint])
                            <label class="flex items-start gap-3 rounded-lg border border-hairline px-3 py-2.5 cursor-pointer transition-colors hover:bg-surface has-[:checked]:border-brand-orange has-[:checked]:bg-brand-orange/5">
                                <input type="checkbox" name="{{ $key }}" value="1" @checked(request()->boolean($key))
                                       class="mt-0.5 rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                <span>
                                    <span class="block text-sm text-brand-ink">{{ $optionLabel }}</span>
                                    <span class="block text-xs text-steel">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="flex items-center justify-between gap-2 px-5 py-4 border-t border-hairline shrink-0">
                <button type="button" @click="clearAdvanced()"
                        class="h-10 inline-flex items-center px-3 text-sm font-medium text-steel rounded-md hover:text-brand-ink hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange">
                    Limpar avançados
                </button>
                <button type="submit"
                        class="h-10 inline-flex items-center gap-2 rounded-md bg-brand-orange px-5 text-sm font-semibold text-brand-ink transition-colors hover:bg-brand-orange-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-ink focus-visible:ring-offset-1 active:scale-[0.98]">
                    <i class="fa-solid fa-check text-xs"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>
</form>
