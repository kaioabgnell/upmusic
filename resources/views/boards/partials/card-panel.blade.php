{{-- Modal centralizado de detalhe/criação de card (estilo Pipefy). Dentro do escopo x-data="kanban(...)". --}}
<div x-show="panelOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4" x-transition.opacity>
    <div class="absolute inset-0 bg-black/40" @click="closePanel()"></div>

    <div class="relative bg-white shadow-xl rounded-lg overflow-hidden flex flex-col md:flex-row w-full max-w-4xl max-h-[90vh]"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        {{-- Coluna principal --}}
        {{-- min-h-0: sem isso, um item flex tem min-height:auto (baseado no conteúdo) por padrão, o
             que anula o overflow-y-auto do "Corpo" mais abaixo quando o modal empilha em coluna no
             mobile — o conteúdo cresce livremente em vez de rolar, "sumindo" com rodapé/anexos. --}}
        <div class="flex-1 min-w-0 min-h-0 flex flex-col">
            {{-- Cabeçalho --}}
            <div class="flex items-center justify-between px-5 h-16 border-b border-hairline shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <h3 class="font-semibold text-brand-ink truncate" x-text="mode === 'create' ? 'Novo card' : ('#' + cardId + ' - ' + (form.title || 'Card'))"></h3>
                    <span x-show="concludedAt" class="shrink-0 text-xs font-medium px-2 py-1 rounded-full bg-brand-ink text-white"><i class="fa-solid fa-circle-check mr-1"></i>Concluído</span>
                    <span x-show="archivedAt" x-cloak class="shrink-0 text-xs font-medium px-2 py-1 rounded-full bg-gray-200 text-gray-700"><i class="fa-solid fa-box-archive mr-1"></i>Arquivado</span>
                    <span x-show="isOverdue" x-cloak class="shrink-0 text-xs font-medium px-2 py-1 rounded-full bg-red-100 text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Vencido</span>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <div class="relative" x-show="mode === 'view' && cardId" x-cloak @click.outside="actionsMenuOpen = false">
                        <button type="button" @click="actionsMenuOpen = !actionsMenuOpen" class="w-8 h-8 inline-flex items-center justify-center rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Mais ações">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div x-show="actionsMenuOpen" x-cloak x-transition
                             class="absolute right-0 top-full mt-1 w-48 bg-white border border-hairline rounded-md shadow-lg z-10 py-1">
                            <button type="button" @click="actionsMenuOpen = false; duplicate()" class="w-full text-left px-3 py-2 text-sm text-brand-ink hover:bg-surface flex items-center gap-2">
                                <i class="fa-regular fa-clone w-4 text-steel"></i> Duplicar Card
                            </button>
                            <button type="button" x-show="!archivedAt" @click="actionsMenuOpen = false; doArchive()" class="w-full text-left px-3 py-2 text-sm text-brand-ink hover:bg-surface flex items-center gap-2">
                                <i class="fa-solid fa-box-archive w-4 text-steel"></i> Arquivar
                            </button>
                            <button type="button" x-show="archivedAt" x-cloak @click="actionsMenuOpen = false; doUnarchive()" class="w-full text-left px-3 py-2 text-sm text-brand-ink hover:bg-surface flex items-center gap-2">
                                <i class="fa-solid fa-box-open w-4 text-steel"></i> Desarquivar
                            </button>
                            <button type="button" @click="actionsMenuOpen = false; shareCard()" class="w-full text-left px-3 py-2 text-sm text-brand-ink hover:bg-surface flex items-center gap-2">
                                <i class="fa-solid fa-share-nodes w-4 text-steel"></i> Compartilhar Card
                            </button>
                            <div class="border-t border-hairline my-1"></div>
                            <button type="button" @click="actionsMenuOpen = false; remove()" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                <i class="fa-solid fa-trash w-4"></i> Excluir
                            </button>
                        </div>
                    </div>
                    <button type="button" @click="closePanel()" class="text-steel hover:text-brand-ink"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
            </div>

            {{-- Loading --}}
            <div x-show="loading" class="flex-1 flex items-center justify-center text-steel">
                <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
            </div>

            {{-- x-show, não x-if: com x-if o <select> de Evento/Empresa é recriado a cada abertura de card e
                 o Alpine aplica x-model antes de o x-for interno criar as <option>s, deixando a seleção em
                 branco mesmo com o valor certo no estado. --}}
            <div x-show="!loading" class="flex-1 min-h-0 flex flex-col">
                    {{-- Faixa de aprovação de etapa (specs/17) — só para card existente numa coluna que exige aprovação. --}}
                    <div x-show="mode === 'view' && cardId && requiresApproval" x-cloak
                         class="mx-5 mt-4 rounded-lg border px-4 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0"
                         :class="canApprove ? 'bg-brand-orange/10 border-brand-orange/30' : 'bg-gray-100 border-hairline'">
                        <div class="flex items-center gap-2 text-sm min-w-0">
                            <i class="fa-solid fa-user-clock" :class="canApprove ? 'text-brand-orange-deep' : 'text-steel'"></i>
                            <span class="font-medium text-brand-ink">Aguardando aprovação</span>
                            <span class="text-steel truncate" x-show="approvers.length" x-text="'(' + approvers.join(', ') + ')'"></span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0" x-show="canApprove" x-cloak>
                            <button type="button" @click="doReject()" class="rounded-md border border-red-300 text-red-600 px-3 py-1.5 text-sm font-medium hover:bg-red-50">
                                <i class="fa-solid fa-xmark"></i> Reprovar
                            </button>
                            <button type="button" @click="doApprove()" class="rounded-md bg-brand-orange px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                                <i class="fa-solid fa-check"></i> Aprovar
                            </button>
                        </div>
                    </div>

                    {{-- Ações rápidas: responsável / vencimento / prioridade --}}
                    <div class="flex flex-wrap items-center gap-2 px-5 py-3 border-b border-hairline shrink-0">
                        {{-- Responsável --}}
                        <div class="relative" @click.outside="assigneeOpen = false">
                            <button type="button" @click="assigneeOpen = !assigneeOpen; assigneeSearch = ''" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-1.5 text-sm hover:border-brand-orange transition-colors">
                                <template x-if="selectedAssignee">
                                    <span class="inline-flex items-center gap-1.5">
                                        <template x-if="selectedAssignee.avatar_url">
                                            <img :src="selectedAssignee.avatar_url" class="w-5 h-5 rounded-full object-cover">
                                        </template>
                                        <template x-if="!selectedAssignee.avatar_url">
                                            <span class="w-5 h-5 rounded-full bg-brand-orange/20 text-brand-orange-deep text-[10px] font-semibold flex items-center justify-center" x-text="initialsOf(selectedAssignee.name)"></span>
                                        </template>
                                    </span>
                                </template>
                                <i x-show="!selectedAssignee" class="fa-regular fa-user text-steel"></i>
                                <span class="text-brand-ink" x-text="selectedAssignee ? selectedAssignee.name : 'Adicionar responsável'"></span>
                            </button>
                            <div x-show="assigneeOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute left-0 z-30 mt-2 w-64 bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-2">
                                <div class="relative mb-2">
                                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                                    <input type="text" x-model="assigneeSearch" placeholder="Pesquisar pessoas" class="w-full pl-7 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                </div>
                                <div class="max-h-48 overflow-y-auto space-y-0.5">
                                    <template x-for="u in filteredAssignees" :key="u.id">
                                        <button type="button" @click="form.assignee_id = u.id; assigneeOpen = false" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm text-left">
                                            <template x-if="u.avatar_url">
                                                <img :src="u.avatar_url" class="w-6 h-6 rounded-full object-cover">
                                            </template>
                                            <template x-if="!u.avatar_url">
                                                <span class="w-6 h-6 rounded-full bg-brand-orange/20 text-brand-orange-deep text-[10px] font-semibold flex items-center justify-center" x-text="initialsOf(u.name)"></span>
                                            </template>
                                            <span class="flex-1 truncate text-brand-ink" x-text="u.name"></span>
                                            <i x-show="Number(form.assignee_id) === u.id" class="fa-solid fa-check text-brand-orange text-xs"></i>
                                        </button>
                                    </template>
                                    <p x-show="filteredAssignees.length === 0" class="text-xs text-steel px-2 py-1.5">Ninguém encontrado.</p>
                                </div>
                                <button type="button" x-show="form.assignee_id" @click="form.assignee_id = ''; assigneeOpen = false" class="w-full text-left px-2 py-1.5 mt-1 text-xs text-red-600 hover:underline border-t border-hairline">Remover responsável</button>
                            </div>
                        </div>

                        {{-- Vencimento --}}
                        <div class="relative" @click.outside="dueOpen = false">
                            <button type="button" @click="dueOpen = !dueOpen" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-1.5 text-sm hover:border-brand-orange transition-colors">
                                <i class="fa-regular fa-calendar text-steel"></i>
                                <span class="text-brand-ink" x-text="form.due_date ? formatDateBR(form.due_date) : 'Vencimento'"></span>
                            </button>
                            <div x-show="dueOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute left-0 z-30 mt-2 bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-3">
                                <input type="date" x-model="form.due_date" class="text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                <button type="button" x-show="form.due_date" @click="form.due_date = ''" class="block mt-2 text-xs text-red-600 hover:underline">Remover vencimento</button>
                            </div>
                        </div>

                        {{-- Prioridade --}}
                        <div class="relative" @click.outside="priorityOpen = false">
                            <button type="button" @click="priorityOpen = !priorityOpen" class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors" :class="priorityMeta(form.priority).classes">
                                <i class="fa-solid fa-flag text-xs"></i>
                                <span x-text="priorityMeta(form.priority).label"></span>
                            </button>
                            <div x-show="priorityOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute left-0 z-30 mt-2 w-40 bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-1">
                                <template x-for="p in ['baixa', 'media', 'alta']" :key="p">
                                    <button type="button" @click="form.priority = p; priorityOpen = false" class="w-full flex items-center px-2 py-1.5 rounded-md hover:bg-surface text-sm text-left">
                                        <span class="w-2 h-2 rounded-full shrink-0" :class="priorityMeta(p).dotClass"></span>
                                        <span class="flex-1 ml-2 text-brand-ink" x-text="priorityMeta(p).label"></span>
                                        <i x-show="form.priority === p" class="fa-solid fa-check text-brand-orange text-xs"></i>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Abas (só ao visualizar/editar um card existente) --}}
                    <div x-show="mode === 'view' && cardId" class="flex border-b border-hairline shrink-0 px-5">
                        <button type="button" @click="tab = 'detalhes'" class="px-3 py-3 text-sm font-medium border-b-2 -mb-px transition-colors" :class="tab === 'detalhes' ? 'border-brand-orange text-brand-ink' : 'border-transparent text-steel hover:text-brand-ink'">Detalhes</button>
                        <button type="button" @click="tab = 'comentarios'" class="px-3 py-3 text-sm font-medium border-b-2 -mb-px transition-colors" :class="tab === 'comentarios' ? 'border-brand-orange text-brand-ink' : 'border-transparent text-steel hover:text-brand-ink'"><span x-text="`Comentários (${comments.length})`"></span></button>
                        <button type="button" @click="tab = 'historico'" class="px-3 py-3 text-sm font-medium border-b-2 -mb-px transition-colors" :class="tab === 'historico' ? 'border-brand-orange text-brand-ink' : 'border-transparent text-steel hover:text-brand-ink'">Histórico</button>
                    </div>

                    {{-- Corpo --}}
                    <div class="flex-1 overflow-y-auto p-5 space-y-5">
                        {{-- Aba: Detalhes (única aba na criação; controlada por aba ao editar) --}}
                        <div x-show="!(mode === 'view' && cardId) || tab === 'detalhes'" class="space-y-5">
                            {{-- Campos fixos --}}
                            <div>
                                <label class="text-sm font-medium text-brand-ink">Título</label>
                                <input type="text" x-model="form.title" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <p class="text-xs text-red-600 mt-1" x-show="errors.title" x-text="errors.title?.[0]"></p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-brand-ink">Descrição</label>
                                <textarea x-model="form.description" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm"></textarea>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-brand-ink flex items-center justify-between">
                                        Empresa
                                        {{-- <button type="button" @click="quickEmpresa()" class="text-xs text-brand-orange-deep hover:underline"><i class="fa-solid fa-plus"></i> nova</button> --}}
                                    </label>
                                    <select x-model="form.empresa_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        <option value="">— Selecione —</option>
                                        <template x-for="e in cfg.empresas" :key="e.id">
                                            <option :value="e.id" x-text="e.corporate_name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-brand-ink">Fornecedor</label>
                                    <div class="relative mt-1" @click.outside="fornecedorOpen = false">
                                        <button type="button" @click="fornecedorOpen = !fornecedorOpen; fornecedorSearch = ''" class="w-full flex items-center justify-between gap-2 rounded-md border border-gray-300 px-3 h-9 text-sm text-left hover:border-brand-orange transition-colors">
                                            <span class="truncate" :class="selectedFornecedor ? 'text-brand-ink' : 'text-steel'" x-text="selectedFornecedor ? selectedFornecedor.name : '— Selecione —'"></span>
                                            <i class="fa-solid fa-chevron-down text-[10px] text-steel shrink-0"></i>
                                        </button>
                                        <div x-show="fornecedorOpen" x-cloak
                                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                             class="absolute left-0 z-30 mt-2 w-72 bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-2">
                                            <div class="relative mb-2">
                                                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                                                <input type="text" x-model="fornecedorSearch" placeholder="Pesquisar fornecedor" class="w-full pl-7 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                            </div>
                                            <div class="max-h-48 overflow-y-auto space-y-0.5">
                                                <template x-for="f in filteredFornecedores" :key="f.id">
                                                    <button type="button" @click="form.fornecedor_id = f.id; fornecedorOpen = false; loadFornecedorHistory(f.id)" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm text-left">
                                                        <span class="flex-1 truncate text-brand-ink" x-text="f.name"></span>
                                                        <span class="text-[11px] text-steel shrink-0" x-text="f.document"></span>
                                                        <i x-show="Number(form.fornecedor_id) === f.id" class="fa-solid fa-check text-brand-orange text-xs"></i>
                                                    </button>
                                                </template>
                                                <p x-show="filteredFornecedores.length === 0" class="text-xs text-steel px-2 py-1.5">Nenhum fornecedor encontrado.</p>
                                            </div>
                                            <div class="border-t border-hairline pt-1 mt-1">
                                                <button type="button" x-show="form.fornecedor_id" @click="form.fornecedor_id = ''; fornecedorOpen = false" class="w-full text-left px-2 py-1.5 text-xs text-red-600 hover:underline">Remover fornecedor</button>
                                                <button type="button" @click="quickFornecedor()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm text-left text-brand-orange-deep font-medium">
                                                    <i class="fa-solid fa-plus text-xs"></i> Novo fornecedor
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-brand-ink">Evento</label>
                                    <select x-model="form.event_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        <option value="">— Nenhum —</option>
                                        <template x-for="ev in cfg.events" :key="ev.id">
                                            <option :value="ev.id" x-text="ev.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <label class="text-sm font-medium text-brand-ink">Valor previsto</label>
                                        {{-- Histórico de preços do fornecedor selecionado (últimos 5 registros). --}}
                                        <div x-show="form.fornecedor_id" x-data="{ historyOpen: false }" @mouseenter="historyOpen = true; positionFornecedorHistoryTooltip($event)" @mouseleave="historyOpen = false">
                                            <i class="fa-solid fa-clock-rotate-left text-steel hover:text-brand-orange cursor-default text-xs"></i>
                                            {{-- position:fixed (não absolute) para escapar do overflow-y-auto do corpo do painel e ficar
                                                 por cima de tudo, sem forçar scroll/quebrar o layout do modal. --}}
                                            <div x-show="historyOpen" x-cloak
                                                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                                 class="fixed z-50 w-64 bg-white border border-hairline rounded-xl shadow-lg p-3"
                                                 :style="`top: ${fornecedorHistoryPos.top}px; left: ${fornecedorHistoryPos.left}px;`">
                                                <p class="text-xs font-semibold text-brand-ink mb-2 truncate" x-text="`Histórico — ${selectedFornecedor?.name ?? ''}`"></p>

                                                <template x-if="fornecedorHistoryLoading && !fornecedorHistory">
                                                    <p class="text-xs text-steel"><i class="fa-solid fa-spinner fa-spin"></i> Carregando...</p>
                                                </template>

                                                <template x-if="fornecedorHistory && fornecedorHistory.records.length === 0">
                                                    <p class="text-xs text-steel">Sem histórico de preços para este fornecedor.</p>
                                                </template>

                                                <template x-if="fornecedorHistory && fornecedorHistory.records.length > 0">
                                                    <div>
                                                        <ul class="space-y-1 mb-2">
                                                            <template x-for="(r, i) in fornecedorHistory.records" :key="i">
                                                                <li class="flex items-center justify-between text-xs">
                                                                    <span class="text-steel" x-text="r.reference_date_br"></span>
                                                                    <span class="font-medium text-brand-ink" x-text="'R$ ' + r.price.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                                                </li>
                                                            </template>
                                                        </ul>
                                                        <div class="border-t border-hairline pt-2 flex items-center justify-between text-xs">
                                                            <span class="text-steel">Média</span>
                                                            <span class="font-semibold text-brand-ink" x-text="'R$ ' + fornecedorHistory.average.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                                        </div>
                                                        <div class="mt-1 flex items-center gap-1.5 text-xs" :class="{ 'text-red-600': fornecedorHistory.trend === 'alta', 'text-green-600': fornecedorHistory.trend === 'baixa', 'text-steel': fornecedorHistory.trend === 'estavel' }">
                                                            <i class="fa-solid" :class="{ 'fa-arrow-trend-up': fornecedorHistory.trend === 'alta', 'fa-arrow-trend-down': fornecedorHistory.trend === 'baixa', 'fa-minus': fornecedorHistory.trend === 'estavel' }"></i>
                                                            <span x-text="{ alta: 'Evolução (alta)', baixa: 'Redução (baixa)', estavel: 'Estável' }[fornecedorHistory.trend]"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="relative mt-1">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
                                        <input type="text" inputmode="decimal" x-model="form.estimated_value" x-mask:dynamic="$money($input, ',')" @blur="checkEstimatedValueVsPrecoInterno()" placeholder="0,00" class="w-full pl-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    </div>
                                    {{-- Aviso comparando com o Preço Interno da categoria do fornecedor selecionado — calculado ao sair do campo. --}}
                                    <p x-show="estimatedValueCheck" x-cloak class="mt-1 text-xs" :class="estimatedValueCheck?.above ? 'text-red-600' : 'text-green-600'" x-text="estimatedValueCheck?.message"></p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-brand-ink">Valor realizado</label>
                                    <div class="relative mt-1">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
                                        <input type="text" inputmode="decimal" x-model="form.actual_value" x-mask:dynamic="$money($input, ',')" placeholder="0,00" class="w-full pl-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    </div>
                                </div>
                            </div>

                            {{-- Campos configuráveis do quadro — dirigido por cfg.fields (não por Blade), já que o
                                 mesmo modal é usado tanto no Kanban de um quadro quanto na listagem global de
                                 cards, onde os campos variam por card (quadro de origem diferente a cada vez). --}}
                            <div x-show="cfg.fields.length" class="border-t border-hairline pt-4 space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-steel">Campos do quadro</p>
                                <template x-for="f in cfg.fields" :key="f.id">
                                    <div>
                                        <label class="text-sm font-medium text-brand-ink">
                                            <span x-text="f.label"></span><span x-show="f.required" class="text-red-600">*</span>
                                        </label>
                                        <template x-if="f.type === 'textarea'">
                                            <textarea x-model="form.fields[f.id]" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm"></textarea>
                                        </template>
                                        <template x-if="f.type === 'select'">
                                            <select x-model="form.fields[f.id]" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                                <option value="">— Selecione —</option>
                                                <template x-for="opt in f.options" :key="opt">
                                                    <option :value="opt" x-text="opt"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="f.type === 'checkbox'">
                                            <div class="mt-1"><input type="checkbox" x-model="form.fields[f.id]" class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"></div>
                                        </template>
                                        <template x-if="f.type === 'date'">
                                            <input type="date" x-model="form.fields[f.id]" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        </template>
                                        <template x-if="f.type === 'number'">
                                            <input type="number" step="any" x-model="form.fields[f.id]" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        </template>
                                        <template x-if="!['textarea', 'select', 'checkbox', 'date', 'number'].includes(f.type)">
                                            <input type="text" x-model="form.fields[f.id]" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        </template>
                                        <p class="text-xs text-red-600 mt-1" x-show="errors['fields.' + f.id]" x-text="errors['fields.' + f.id]?.[0]"></p>
                                    </div>
                                </template>
                            </div>

                            {{-- Seções apenas de card existente --}}
                            <template x-if="mode === 'view' && cardId">
                                <div class="space-y-5">
                                    {{-- Card concluído: só a opção de reabrir e enviar para um quadro. --}}
                                    <div x-show="concludedAt" class="border-t border-hairline pt-4 rounded-lg bg-brand-orange/5 p-3 -mx-1">
                                        <p class="text-xs text-steel mb-2">Concluído em <span x-text="concludedAt"></span><template x-if="concludedBy"> por <span x-text="concludedBy"></span></template></p>
                                        <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-rotate-left text-brand-orange mr-1"></i> Reabrir e enviar para um quadro</p>
                                        <div class="flex gap-2">
                                            <select x-model="transferBoardId" class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                                <option value="">— Selecione o quadro —</option>
                                                <template x-for="b in cfg.boards" :key="b.id">
                                                    <option :value="b.id" x-text="b.name"></option>
                                                </template>
                                            </select>
                                            <button type="button" @click="doReopen()" :disabled="!transferBoardId" class="rounded-md bg-brand-orange px-3 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-40">Reabrir</button>
                                        </div>
                                    </div>

                                    {{-- Transferência / Conclusão (card ativo, só na etapa Final do quadro) --}}
                                    <div x-show="!concludedAt && isFinalColumn" class="border-t border-hairline pt-4 rounded-lg bg-brand-orange/5 p-3 -mx-1 space-y-3">
                                        <div>
                                            <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-arrow-right-arrow-left text-brand-orange mr-1"></i> Enviar para outro departamento</p>
                                            <div class="flex gap-2">
                                                <select x-model="transferBoardId" class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                                    <option value="">— Selecione o quadro —</option>
                                                    <template x-for="b in cfg.transferBoards" :key="b.id">
                                                        <option :value="b.id" x-text="b.name"></option>
                                                    </template>
                                                </select>
                                                <button type="button" @click="doTransfer()" :disabled="!transferBoardId" class="rounded-md bg-brand-orange px-3 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-40">Enviar</button>
                                            </div>
                                        </div>
                                        <div class="border-t border-hairline/60 pt-3">
                                            <button type="button" @click="doConclude()" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-ink px-3 py-2 text-sm font-semibold text-white hover:bg-black">
                                                <i class="fa-solid fa-circle-check"></i> Concluir card
                                            </button>
                                            <p class="text-xs text-steel mt-1.5">O card deixará de aparecer em qualquer quadro. Pode ser reaberto depois em "Todos os cards".</p>
                                        </div>
                                    </div>

                                    {{-- Anexos --}}
                                    <div class="border-t border-hairline pt-4">
                                        <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-paperclip text-steel mr-1"></i> Anexos</p>
                                        <div class="space-y-2">
                                            <template x-for="a in attachments" :key="a.id">
                                                <div class="rounded-md border border-hairline p-2 text-sm">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fa-solid fa-file text-steel"></i>
                                                        <a :href="a.url" class="flex-1 text-brand-ink hover:underline truncate" x-text="a.original_name"></a>
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600" x-text="a.kind_label"></span>
                                                        <button type="button" @click="deleteAttachment(a)" class="text-steel hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
                                                    </div>
                                                    {{-- Observação do fornecedor ao enviar a minuta (specs/19) — null para os demais anexos. --}}
                                                    <p x-show="a.note" x-cloak class="text-xs text-steel italic mt-1 pl-6">"<span x-text="a.note"></span>"</p>
                                                </div>
                                            </template>
                                            <p x-show="attachments.length === 0" class="text-xs text-steel">Nenhum anexo.</p>
                                        </div>
                                        <div class="flex items-center gap-2 mt-2">
                                            <select x-model="uploadKind" class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                                <option value="geral">Geral</option>
                                                <option value="nota_fiscal">Nota fiscal</option>
                                                <option value="comprovante">Comprovante</option>
                                            </select>
                                            <label class="inline-flex items-center gap-2 rounded-md border border-dashed border-hairline px-3 py-1.5 text-xs font-medium text-steel cursor-pointer hover:border-brand-orange">
                                                <i class="fa-solid fa-cloud-arrow-up"></i> Enviar arquivo
                                                <input type="file" class="hidden" @change="uploadAttachment($event)">
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Formulário do fornecedor (minuta) — specs/19. Só aparece quando o quadro permite. --}}
                                    <div x-show="supplierForm.allowed" x-cloak class="border-t border-hairline pt-4">
                                        <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-file-signature text-steel mr-1"></i> Formulário do fornecedor (minuta)</p>

                                        {{-- Sem link ainda --}}
                                        <div x-show="!supplierForm.active">
                                            <p class="text-xs text-steel mb-2">Gere um link para o fornecedor enviar a própria minuta. O arquivo cai como anexo neste card.</p>
                                            <button type="button" @click="generateSupplierLink()" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-3 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                                                <i class="fa-solid fa-link"></i> Gerar link para o fornecedor
                                            </button>
                                        </div>

                                        {{-- Link ativo --}}
                                        <div x-show="supplierForm.active" x-cloak class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <input type="text" :value="supplierForm.url" readonly class="flex-1 border-gray-300 bg-surface rounded-md text-xs text-steel" @focus="$event.target.select()">
                                                <button type="button" @click="copySupplierLink()" class="shrink-0 rounded-md border border-hairline px-3 py-2 text-xs font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-copy"></i> Copiar</button>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-steel"><i class="fa-solid fa-inbox mr-1"></i><span x-text="minutaCount"></span> minuta(s) recebida(s)</span>
                                                <button type="button" @click="disableSupplierLink()" class="text-xs text-red-600 hover:underline"><i class="fa-solid fa-ban mr-1"></i>Desativar link</button>
                                            </div>
                                            <p class="text-[11px] text-steel">As minutas enviadas aparecem na seção de Anexos acima (tipo "Minuta").</p>
                                        </div>
                                    </div>

                                    {{-- Mover card para fase (mobile): no desktop isso vive na rail lateral (<aside>
                                         abaixo, oculta aqui via md:hidden) — no mobile não há espaço para uma coluna
                                         lateral, então o mesmo conteúdo aparece aqui, abaixo de tudo (depois dos anexos). --}}
                                    <div x-show="previousColumns.length || nextColumns.length" x-cloak class="md:hidden border-t border-hairline pt-4">
                                        <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-arrows-left-right text-steel mr-1.5"></i> Mover card para fase</p>
                                        <div class="space-y-2">
                                            <template x-for="col in previousColumns" :key="'prev-mobile-' + col.id">
                                                <button type="button" @click="moveToColumn(col.id)" class="w-full flex items-center justify-between gap-2 rounded-md px-3 py-2.5 text-sm font-medium transition-transform hover:scale-[1.02]" :style="columnPillStyle(col.color)">
                                                    <span class="truncate" x-text="col.name"></span>
                                                    <i class="fa-solid fa-arrow-right text-xs shrink-0"></i>
                                                </button>
                                            </template>
                                            <template x-for="col in nextColumns" :key="'next-mobile-' + col.id">
                                                <button type="button" @click="moveToColumn(col.id)" class="w-full flex items-center justify-between gap-2 rounded-md px-3 py-2.5 text-sm font-medium transition-transform hover:scale-[1.02]" :style="columnPillStyle(col.color)">
                                                    <span class="truncate" x-text="col.name"></span>
                                                    <i class="fa-solid fa-arrow-right text-xs shrink-0"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Aba: Comentários --}}
                        <template x-if="mode === 'view' && cardId">
                            <div x-show="tab === 'comentarios'">
                                <div class="flex gap-2 mb-3">
                                    <textarea x-model="newComment" rows="1" placeholder="Escreva um comentário..." class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm"></textarea>
                                    <button type="button" @click="addComment()" class="rounded-md bg-brand-ink px-3 text-sm text-white hover:bg-black"><i class="fa-solid fa-paper-plane"></i></button>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="c in comments" :key="c.id">
                                        <div class="text-sm">
                                            <div class="flex items-center gap-2 text-xs text-steel">
                                                <span class="font-medium text-brand-ink" x-text="c.user"></span>
                                                <span x-text="c.created_at"></span>
                                            </div>
                                            <p class="text-brand-ink whitespace-pre-line" x-text="c.body"></p>
                                        </div>
                                    </template>
                                    <p x-show="comments.length === 0" class="text-xs text-steel">Nenhum comentário.</p>
                                </div>
                            </div>
                        </template>

                        {{-- Aba: Histórico --}}
                        <template x-if="mode === 'view' && cardId">
                            <div x-show="tab === 'historico'" class="space-y-2">
                                <template x-for="(m, i) in movements" :key="i">
                                    <div class="flex items-start gap-2 text-xs text-steel">
                                        <i class="fa-solid fa-circle text-[6px] mt-1.5 text-brand-orange"></i>
                                        <div>
                                            <span class="text-brand-ink" x-text="m.type_label"></span>:
                                            <span x-text="m.from"></span> <i class="fa-solid fa-arrow-right text-[10px]"></i> <span x-text="m.to"></span>
                                            <div><span x-text="m.user"></span> · <span x-text="m.created_at"></span></div>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="movements.length === 0" class="text-xs text-steel">Sem movimentações.</p>
                            </div>
                        </template>
                    </div>
            </div>

            {{-- Rodapé --}}
            <div x-show="!loading" class="flex items-center justify-end gap-2 px-5 py-4 border-t border-hairline shrink-0">
                <button type="button" @click="closePanel()" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Fechar</button>
                <button type="button" @click="save()" :disabled="saving" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-50">
                    <i class="fa-solid fa-floppy-disk"></i> <span x-text="saving ? 'Salvando...' : 'Salvar'"></span>
                </button>
            </div>
        </div>

        {{-- Rail direita: Mover card para fase (só desktop — no mobile, o mesmo conteúdo aparece
             embutido no formulário, logo abaixo dos anexos; ver bloco "md:hidden" acima). --}}
        <aside x-show="!loading && mode === 'view' && cardId && (previousColumns.length || nextColumns.length)" class="hidden md:flex md:w-64 md:shrink-0 border-l border-hairline flex-col bg-surface/40">
            <div class="flex items-center h-16 px-4 border-b border-hairline shrink-0">
                <p class="text-sm font-semibold text-brand-ink truncate"><i class="fa-solid fa-arrows-left-right text-steel mr-1.5"></i> Mover card para fase</p>
            </div>
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <template x-for="col in previousColumns" :key="'prev-' + col.id">
                    <button type="button" @click="moveToColumn(col.id)" class="w-full flex items-center justify-between gap-2 rounded-md px-3 py-2.5 text-sm font-medium transition-transform hover:scale-[1.02]" :style="columnPillStyle(col.color)">
                        <span class="truncate" x-text="col.name"></span>
                        <i class="fa-solid fa-arrow-right text-xs shrink-0"></i>
                    </button>
                </template>
                <template x-for="col in nextColumns" :key="'next-' + col.id">
                    <button type="button" @click="moveToColumn(col.id)" class="w-full flex items-center justify-between gap-2 rounded-md px-3 py-2.5 text-sm font-medium transition-transform hover:scale-[1.02]" :style="columnPillStyle(col.color)">
                        <span class="truncate" x-text="col.name"></span>
                        <i class="fa-solid fa-arrow-right text-xs shrink-0"></i>
                    </button>
                </template>
            </div>
        </aside>
    </div>
</div>
