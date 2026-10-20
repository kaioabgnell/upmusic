{{-- Drawer do Pedido de Locação (specs/24 §8.3) — Dados / Itens / Financeiro / Histórico.
     Compartilha o escopo x-data="rentalsBoard(...)" da página que o inclui. --}}
<div x-show="drawer.open" x-cloak class="fixed inset-0 z-50 flex justify-end" x-transition.opacity>
    <div class="absolute inset-0 bg-black/40" @click="closeDrawer()"></div>

    <div class="relative w-full max-w-2xl bg-white h-full shadow-xl flex flex-col"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">

        <div x-show="drawer.loading" class="flex-1 flex items-center justify-center text-steel">
            <i class="fa-solid fa-circle-notch fa-spin text-xl"></i>
        </div>

        <template x-if="!drawer.loading && drawer.order">
            <div class="flex flex-col h-full" x-data="{ get o() { return drawer.order; } }">
                {{-- Cabeçalho --}}
                <div class="px-5 py-4 border-b border-hairline shrink-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-brand-ink" x-text="o.code"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand-ink text-white" x-text="statusLabel(o.status)"></span>
                                <span x-show="o.is_locked" class="text-xs text-steel"><i class="fa-solid fa-lock"></i></span>
                            </div>
                            <p class="text-xs text-steel mt-0.5" x-text="o.client_label || 'Sem cliente definido'"></p>
                        </div>
                        <button type="button" @click="closeDrawer()" class="text-steel hover:text-brand-ink shrink-0">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- Ações da etapa --}}
                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <button type="button" x-show="o.status === 'orcamento'" @click="confirmOrder()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md bg-brand-orange text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-circle-check mr-1"></i> Confirmar
                        </button>
                        <button type="button" x-show="o.status === 'confirmada'" @click="moveStatus(o, 'em_uso')"
                                class="px-3 py-1.5 text-xs font-medium rounded-md bg-brand-orange text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-truck-ramp-box mr-1"></i> Marcar como Em uso
                        </button>
                        <button type="button" x-show="o.status === 'em_uso'" @click="openReturnModal()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md bg-brand-orange text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Conferir devolução
                        </button>
                        <button type="button" x-show="o.status === 'devolvida' && cfg.canManage" @click="openCloseModal()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md bg-brand-orange text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-lock mr-1"></i> Encerrar
                        </button>
                        <button type="button" x-show="!['encerrada','cancelada'].includes(o.status)" @click="cancelOrder()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md border border-hairline text-steel hover:bg-surface">
                            <i class="fa-solid fa-ban mr-1"></i> Cancelar
                        </button>
                        <button type="button" x-show="o.status === 'encerrada' && cfg.canManage" @click="reopenOrder()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md border border-hairline text-steel hover:bg-surface">
                            <i class="fa-solid fa-lock-open mr-1"></i> Reabrir
                        </button>
                        <button type="button" x-show="o.status === 'cancelada' && cfg.canManage" @click="reactivateOrder()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md border border-hairline text-steel hover:bg-surface">
                            <i class="fa-solid fa-rotate-right mr-1"></i> Reativar
                        </button>
                        <button type="button" x-show="cfg.canManage && ['orcamento','cancelada'].includes(o.status)" @click="deleteOrder()"
                                class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash mr-1"></i> Excluir
                        </button>
                    </div>

                    <div class="mt-3 flex items-center gap-1">
                        <button type="button" @click="drawer.tab = 'dados'" class="px-3 py-1.5 text-xs font-medium rounded-md" :class="drawer.tab === 'dados' ? 'bg-brand-ink text-white' : 'text-steel hover:bg-surface'">Dados</button>
                        <button type="button" @click="drawer.tab = 'itens'" class="px-3 py-1.5 text-xs font-medium rounded-md" :class="drawer.tab === 'itens' ? 'bg-brand-ink text-white' : 'text-steel hover:bg-surface'">Itens</button>
                        <button type="button" @click="drawer.tab = 'financeiro'" class="px-3 py-1.5 text-xs font-medium rounded-md" :class="drawer.tab === 'financeiro' ? 'bg-brand-ink text-white' : 'text-steel hover:bg-surface'">Financeiro</button>
                        <button type="button" @click="drawer.tab = 'historico'" class="px-3 py-1.5 text-xs font-medium rounded-md" :class="drawer.tab === 'historico' ? 'bg-brand-ink text-white' : 'text-steel hover:bg-surface'">Histórico</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4">

                    {{-- ---- Dados ---- --}}
                    <div x-show="drawer.tab === 'dados'" class="space-y-3">
                        <div>
                            <label class="text-xs text-steel">Cliente (empresa)</label>
                            <select x-model="o.empresa_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <option value="">— Selecionar —</option>
                                @foreach ($empresas as $e)
                                    <option value="{{ $e->id }}">{{ $e->trade_name ?: $e->corporate_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-steel">Ou cliente avulso (nome)</label>
                            <input type="text" x-model="o.client_name" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-steel">Local / evento</label>
                            <input type="text" x-model="o.location" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-steel">Retirada</label>
                                <input type="datetime-local" x-model="o.start_at" @change="confirmRecalculateDailyCounts()" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-steel">Devolução</label>
                                <input type="datetime-local" x-model="o.end_at" @change="confirmRecalculateDailyCounts()" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-steel">Desconto</label>
                                <div class="mt-1 flex gap-2">
                                    <select x-model="o.discount_type" class="w-24 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        <option value="">Nenhum</option>
                                        <option value="valor">R$</option>
                                        <option value="percentual">%</option>
                                    </select>
                                    <input type="text" inputmode="decimal" :value="o.discount_value" @input="maskInto(o, 'discount_value', $event)"
                                           class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-steel">Vencimento da cobrança</label>
                                <input type="date" x-model="o.revenue_due_date" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-steel">Observações</label>
                            <textarea x-model="o.notes" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm"></textarea>
                        </div>
                        <button type="button" @click="saveOrderData()" :disabled="drawer.saving"
                                class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-50">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar dados
                        </button>
                    </div>

                    {{-- ---- Itens ---- --}}
                    <div x-show="drawer.tab === 'itens'" class="space-y-4">
                        <div class="space-y-2">
                            <template x-for="line in o.items" :key="line.id">
                                <div class="rounded-md border border-hairline p-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-brand-ink" x-text="line.item_name"></span>
                                        <span class="text-sm font-medium text-brand-ink" x-text="brl(line.total)"></span>
                                    </div>
                                    <div class="mt-1.5 grid grid-cols-3 gap-2 items-center">
                                        <input type="number" min="1" x-model.number="line.quantity" @change="updateLine(line)"
                                               class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" placeholder="Qtd">
                                        <input type="number" min="1" x-model.number="line.daily_count" @change="updateLine(line)"
                                               class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" placeholder="Diárias">
                                        <input type="text" inputmode="decimal" :value="line.unit_price"
                                               @input="line.unit_price = maskMoneyDigits($event.target.value)" @change="updateLine(line)"
                                               class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" placeholder="Unitário">
                                    </div>
                                    <div class="mt-1 flex items-center justify-between">
                                        <span class="text-[11px] text-steel" x-show="line.available_excluding_self !== null">
                                            Disponível no período: <span x-text="line.available_excluding_self"></span>
                                            <span class="text-brand-orange-deep font-medium" x-show="line.quantity > line.available_excluding_self">
                                                (faltam <span x-text="line.quantity - line.available_excluding_self"></span>)
                                            </span>
                                        </span>
                                        <button type="button" @click="removeLine(line)" class="text-steel hover:text-red-600">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <p x-show="o.items.length === 0" class="text-xs text-steel">Nenhum item adicionado ainda.</p>
                        </div>

                        <div class="border-t border-hairline pt-4 space-y-2">
                            <p class="text-xs font-semibold text-brand-ink">Adicionar item</p>
                            <select x-model="newLine.rental_item_id" @change="onPickLine(newLine.rental_item_id)"
                                    class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <option value="">— Selecionar item —</option>
                                <template x-for="i in cfg.items" :key="i.id">
                                    <option :value="i.id" x-text="i.name"></option>
                                </template>
                            </select>
                            <p class="text-[11px]" x-show="lineAvailability !== null"
                               :class="newLine.quantity > lineAvailability ? 'text-brand-orange-deep' : 'text-steel'">
                                Disponível no período: <span x-text="lineAvailability"></span>
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <input type="number" min="1" x-model.number="newLine.quantity" @change="checkLineAvailability()" placeholder="Qtd"
                                       class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <input type="number" min="1" x-model.number="newLine.daily_count" placeholder="Diárias (auto)"
                                       class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <input type="text" inputmode="decimal" :value="newLine.unit_price"
                                       @input="newLine.unit_price = maskMoneyDigits($event.target.value)" placeholder="Unitário"
                                       class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            </div>
                            <button type="button" @click="addLine()" :disabled="!newLine.rental_item_id"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-50">
                                <i class="fa-solid fa-plus"></i> Adicionar
                            </button>
                        </div>

                        <div class="rounded-md bg-surface border border-hairline p-3 text-sm space-y-1">
                            <div class="flex items-center justify-between"><span class="text-steel">Subtotal</span><span x-text="brl(o.subtotal)"></span></div>
                            <div class="flex items-center justify-between font-semibold"><span class="text-brand-ink">Receita prevista</span><span x-text="brl(o.revenue_estimated)"></span></div>
                        </div>
                    </div>

                    {{-- ---- Financeiro ---- --}}
                    <div x-show="drawer.tab === 'financeiro'" class="space-y-5" x-data="{ get s() { return o.summary; } }">
                        <div class="rounded-md bg-surface border border-hairline p-3 text-sm space-y-1">
                            <div class="flex items-center justify-between"><span class="text-steel">Receita prevista</span><span x-text="brl(s.revenue.estimated)"></span></div>
                            <div class="flex items-center justify-between"><span class="text-steel">Receita realizada</span><span x-text="s.revenue.actual === null ? '—' : brl(s.revenue.actual)"></span></div>
                            <div class="flex items-center justify-between"><span class="text-steel">Recebido</span><span x-text="brl(s.revenue.received)"></span></div>
                            <div class="flex items-center justify-between"><span class="font-medium" :class="s.revenue.receivable > 0 ? 'text-brand-orange-deep' : 'text-brand-ink'">A receber</span><span :class="s.revenue.receivable > 0 ? 'text-brand-orange-deep font-medium' : 'text-brand-ink'" x-text="brl(s.revenue.receivable)"></span></div>
                            <div class="border-t border-hairline my-1"></div>
                            <div class="flex items-center justify-between"><span class="text-steel">Custos previstos</span><span x-text="brl(s.costs.estimated)"></span></div>
                            <div class="flex items-center justify-between"><span class="text-steel">Falta pagar</span><span x-text="brl(s.costs.pending)"></span></div>
                            <div class="border-t border-hairline my-1"></div>
                            <div class="flex items-center justify-between font-semibold"><span class="text-brand-ink">Resultado previsto</span><span x-text="brl(s.result.estimated)"></span></div>
                            <div class="flex items-center justify-between font-semibold" x-show="s.result.actual !== null"><span class="text-brand-ink">Resultado realizado</span><span x-text="brl(s.result.actual)"></span></div>
                        </div>

                        {{-- Recebimentos --}}
                        <div>
                            <p class="text-xs font-semibold text-brand-ink mb-2">Recebimentos</p>
                            <div class="space-y-2">
                                <template x-for="r in o.receipts" :key="r.id">
                                    <div class="flex items-center gap-2 rounded-md border border-hairline p-2.5">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-brand-ink" x-text="r.source || 'Sem fonte'"></p>
                                            <p class="text-[11px] text-steel" x-text="r.received_at_label"></p>
                                        </div>
                                        <span class="text-sm font-medium text-brand-ink" x-text="brl(r.amount)"></span>
                                        <button type="button" @click="removeReceipt(r)" class="text-steel hover:text-red-600">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                                <p x-show="o.receipts.length === 0" class="text-xs text-steel">Nenhum recebimento lançado.</p>
                            </div>
                            <div class="border-t border-hairline mt-3 pt-3 space-y-2">
                                <select x-model="newReceipt.finance_payment_source_id" class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    <option value="">Fonte do recebimento</option>
                                    @foreach ($sources as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" inputmode="decimal" :value="newReceipt.amount" @input="maskInto(newReceipt, 'amount', $event)" placeholder="0,00"
                                           class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    <input type="date" x-model="newReceipt.received_at" class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                </div>
                                <button type="button" @click="addReceipt()" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                                    <i class="fa-solid fa-plus"></i> Registrar recebimento
                                </button>
                            </div>
                        </div>

                        {{-- Custos (sublocação) --}}
                        <div class="border-t border-hairline pt-4">
                            <p class="text-xs font-semibold text-brand-ink mb-2">Custos (sublocação)</p>
                            <div class="space-y-3">
                                <template x-for="c in o.costs" :key="c.id">
                                    <div class="rounded-md border border-hairline p-2.5 space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <input type="text" x-model="c.description" @change="updateCost(c)" class="flex-1 border-0 p-0 text-sm font-medium text-brand-ink focus:ring-0">
                                            <button type="button" @click="removeCost(c)" class="text-steel hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="text-[10px] text-steel">Previsto</label>
                                                <input type="text" inputmode="decimal" :value="c.estimated_value" @input="c.estimated_value = maskMoneyDigits($event.target.value)" @change="updateCost(c)"
                                                       class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                            </div>
                                            <div>
                                                <label class="text-[10px] text-steel">Realizado</label>
                                                <input type="text" inputmode="decimal" :value="c.actual_value" @input="c.actual_value = maskMoneyDigits($event.target.value)" @change="updateCost(c)"
                                                       class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px] text-steel">
                                            <span>Pago: <span x-text="brl(c.paid)"></span></span>
                                            <span :class="c.pending > 0 ? 'text-brand-orange-deep font-medium' : ''">Falta pagar: <span x-text="brl(c.pending)"></span></span>
                                        </div>
                                        <template x-for="p in c.payments" :key="p.id">
                                            <div class="flex items-center gap-2 text-xs pl-2 border-l-2 border-hairline">
                                                <span class="flex-1" x-text="`${p.source || 'Sem fonte'} · ${p.paid_at_label}`"></span>
                                                <span x-text="brl(p.amount)"></span>
                                                <button type="button" @click="removeCostPayment(p)" class="text-steel hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                                            </div>
                                        </template>
                                        <div class="flex items-center gap-1.5 pt-1" x-data="{ form: (newCostPayment[c.id] = newCostPayment[c.id] || { amount: '', paid_at: '', finance_payment_source_id: '' }) }">
                                            <select x-model="form.finance_payment_source_id" class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                                <option value="">Fonte</option>
                                                @foreach ($sources as $s)
                                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" inputmode="decimal" :value="form.amount" @input="form.amount = maskMoneyDigits($event.target.value)" placeholder="0,00"
                                                   class="w-20 text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                            <input type="date" x-model="form.paid_at" class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                            <button type="button" @click="addCostPayment(c)" class="text-brand-orange-deep hover:underline text-xs font-medium">Pagar</button>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="o.costs.length === 0" class="text-xs text-steel">Nenhum custo lançado.</p>
                            </div>
                            <div class="border-t border-hairline mt-3 pt-3 space-y-2">
                                <input type="text" x-model="newCost.description" placeholder="Descrição (ex.: sublocação de 10 mesas)"
                                       class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <input type="text" inputmode="decimal" :value="newCost.estimated_value" @input="maskInto(newCost, 'estimated_value', $event)" placeholder="Valor previsto"
                                       class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                <button type="button" @click="addCost()" :disabled="!newCost.description"
                                        class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-50">
                                    <i class="fa-solid fa-plus"></i> Lançar custo
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ---- Histórico ---- --}}
                    <div x-show="drawer.tab === 'historico'" class="space-y-2">
                        <p class="text-xs text-steel" x-show="o.closed_at">Encerrado em <span x-text="o.closed_at"></span> por <span x-text="o.closed_by"></span>.</p>
                        <p class="text-xs text-steel" x-show="o.cancel_reason">Motivo do cancelamento: <span x-text="o.cancel_reason"></span></p>
                        <p class="text-xs text-steel">Histórico detalhado disponível ao reabrir o pedido futuramente.</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- Modal: conferência de devolução --}}
<div x-show="returnOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="returnOpen = false"></div>
    <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl p-6 space-y-4" x-show="drawer.order">
        <h3 class="text-base font-semibold text-brand-ink">Conferência de devolução</h3>
        <div class="space-y-2 max-h-80 overflow-y-auto">
            <template x-for="line in (drawer.order?.items || [])" :key="line.id">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm text-brand-ink truncate" x-text="`${line.item_name} (locado: ${line.quantity})`"></span>
                    <input type="number" min="0" x-model.number="returnForm[line.id]" class="w-20 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
            </template>
        </div>
        <div class="flex items-center justify-end gap-2">
            <button type="button" @click="returnOpen = false" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
            <button type="button" @click="submitReturn()" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                <i class="fa-solid fa-clipboard-check"></i> Confirmar devolução
            </button>
        </div>
    </div>
</div>

{{-- Modal: encerramento --}}
<div x-show="closeOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="closeOpen = false"></div>
    <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl p-6 space-y-4" x-show="drawer.order">
        <h3 class="text-base font-semibold text-brand-ink">Encerrar pedido</h3>
        <p class="text-xs text-steel">Informe a receita realizada e o realizado de cada custo (0 é aceito).</p>
        <button type="button" @click="copyEstimatedToActual()" class="text-xs text-brand-orange-deep font-medium hover:underline">
            Copiar previsto para realizado
        </button>
        <div>
            <label class="text-xs text-steel">Receita realizada</label>
            <input type="text" inputmode="decimal" :value="closeForm.revenue_actual" @input="closeForm.revenue_actual = maskMoneyDigits($event.target.value)"
                   class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
        </div>
        <div class="space-y-2 max-h-56 overflow-y-auto">
            <template x-for="c in (drawer.order?.costs || [])" :key="c.id">
                <div>
                    <label class="text-xs text-steel" x-text="c.description"></label>
                    <input type="text" inputmode="decimal" :value="closeForm.costs[c.id]" @input="closeForm.costs[c.id] = maskMoneyDigits($event.target.value)"
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
            </template>
        </div>
        <div class="flex items-center justify-end gap-2">
            <button type="button" @click="closeOpen = false" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
            <button type="button" @click="submitClose()" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                <i class="fa-solid fa-lock"></i> Encerrar
            </button>
        </div>
    </div>
</div>
