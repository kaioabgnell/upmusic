{{-- Ficha do registro (ícone "ver" na grade de Custos). Faz parte do escopo
     x-data="financeCosts(...)": edita o MESMO objeto da linha — o que muda aqui muda na grade
     na hora, e o autosave é o mesmo (touch/save). Traz também o que saiu da grade para deixá-la
     legível: ART, Autorizado por, documentos (Controle) e observações. --}}
@php
    $fieldClass = 'mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm disabled:bg-gray-50 disabled:text-gray-500';
    $labelClass = 'block text-xs font-medium text-steel';
@endphp
<div x-show="detail.open" x-cloak class="fixed inset-0 z-50 flex items-stretch sm:items-center justify-center sm:p-6"
     @keydown.escape.window="if (detail.open && !picker.open) closeDetail()"
     role="dialog" aria-modal="true" aria-labelledby="cost-detail-title">
    <div class="absolute inset-0 bg-black/40" @click="closeDetail()" x-show="detail.open" x-transition.opacity></div>

    <template x-if="detail.row">
        <div class="relative flex w-full max-w-3xl flex-col bg-white shadow-xl sm:max-h-[90vh] sm:rounded-2xl"
             x-show="detail.open"
             x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
             x-transition:enter-start="opacity-0 translate-y-2 sm:scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

            {{-- Identidade da linha: o que é, de onde veio e o estado do salvamento. --}}
            <div class="shrink-0 border-b border-hairline px-5 pt-4 pb-3 sm:px-6">
                <div class="flex items-center gap-2 text-xs text-steel">
                    <span class="truncate" x-text="categoryLabel(detail.row) ?? 'Sem item'"></span>
                    <a x-show="detail.row.card_id" :href="cardUrl(detail.row)" target="_blank" rel="noopener"
                       :title="`Abrir o card #${detail.row.card_id} em nova aba`"
                       class="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-orange/15 px-1.5 py-0.5 text-[10px] text-brand-orange-deep hover:bg-brand-orange/25">
                        <span x-text="`card #${detail.row.card_id}`"></span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
                    <span class="ml-auto flex shrink-0 items-center gap-1.5" aria-live="polite">
                        <template x-if="savingIds.includes(detail.row.id)">
                            <span><i class="fa-solid fa-circle-notch fa-spin mr-1"></i>Salvando</span>
                        </template>
                        <template x-if="!savingIds.includes(detail.row.id) && savedIds.includes(detail.row.id)">
                            <span class="text-green-700"><i class="fa-solid fa-check mr-1"></i>Salvo</span>
                        </template>
                    </span>
                    <button type="button" @click="closeDetail()" class="ml-2 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Fechar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <label for="cost-detail-title" class="sr-only">Descrição</label>
                <input id="cost-detail-title" type="text" data-autofocus x-model="detail.row.description" @input="touch(detail.row)"
                       :disabled="readonly" maxlength="180" :list="`presets-${detail.row.fornecedor_categoria_id || 'none'}`"
                       class="mt-1 w-full border-0 border-b border-transparent px-0 py-1 text-lg font-semibold text-brand-ink focus:border-brand-orange focus:ring-0 disabled:bg-transparent">
            </div>

            <div class="flex-1 overflow-y-auto">
                {{-- O caminho do dinheiro desta linha, da previsão ao que falta pagar. A barra mostra
                     quanto do realizado já saiu — é a leitura que a grade inteira não dá de relance. --}}
                <div class="bg-surface px-5 py-4 sm:px-6">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs text-steel" x-text="detail.row.unit_estimated_2 === null || detail.row.unit_estimated_2 === '' ? 'Previsto' : 'Previsto (2ª estimativa)'"></dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-brand-ink" x-text="brl(detail.row.current_estimate)"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-steel">Realizado</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-brand-ink"
                                x-text="detail.row.unit_actual === '' || detail.row.unit_actual === null ? '—' : brl(detail.row.total_actual)"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-steel">Pago</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-brand-ink" x-text="brl(detail.row.paid)"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-steel" x-text="detail.row.pending < 0 ? 'Pago a mais' : 'Falta pagar'"></dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums"
                                :class="detail.row.pending > 0 ? 'text-brand-orange-deep' : (detail.row.pending < 0 ? 'text-red-600' : 'text-brand-ink')"
                                x-text="brl(Math.abs(detail.row.pending))"></dd>
                        </div>
                    </dl>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-hairline" role="progressbar"
                         :aria-valuenow="paidPercent(detail.row)" aria-valuemin="0" aria-valuemax="100" aria-label="Quanto do realizado já foi pago">
                        <div class="h-full rounded-full transition-[width] duration-300 motion-reduce:transition-none"
                             :class="detail.row.pending < 0 ? 'bg-red-500' : 'bg-brand-orange'"
                             :style="`width:${paidPercent(detail.row)}%`"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-steel">
                        <span x-text="`${paidPercent(detail.row)}% do realizado pago`"></span><template x-if="detail.row.due_date">
                            <span :class="isDueHighlighted(detail.row.due_date) && detail.row.pending > 0 ? 'text-red-600 font-medium' : ''"
                                  x-text="`, vence em ${detail.row.due_date.split('-').reverse().join('/')}`"></span>
                        </template>
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-x-8 gap-y-6 px-5 py-5 sm:grid-cols-2 sm:px-6">
                    {{-- Classificação: o que é e com quem --}}
                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-brand-ink">Classificação</h3>
                        <div>
                            <label class="{{ $labelClass }}" for="cd-item">Item</label>
                            <select id="cd-item" x-model="detail.row.fornecedor_categoria_id" @change="save(detail.row)" :disabled="readonly" class="{{ $fieldClass }}">
                                <option value="">Sem item</option>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="cd-center">Centro de custo</label>
                            <select id="cd-center" x-model="detail.row.cost_center_id" @change="save(detail.row)" :disabled="readonly" class="{{ $fieldClass }}">
                                <option value="">Sem centro de custo</option>
                                @foreach ($costCenters as $centro)
                                    @if ($centro['active'])
                                        <option value="{{ $centro['id'] }}">{{ $centro['label'] }}</option>
                                    @else
                                        <option value="{{ $centro['id'] }}"
                                                :hidden="detail.row.cost_center_id != {{ $centro['id'] }}"
                                                :disabled="detail.row.cost_center_id != {{ $centro['id'] }}">{{ $centro['label'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="cd-supplier">Empresa</label>
                            <select id="cd-supplier" x-model="detail.row.fornecedor_id" @change="save(detail.row)" :disabled="readonly" class="{{ $fieldClass }}">
                                <option value="">Sem empresa</option>
                                @foreach ($fornecedores as $fornecedor)
                                    <option value="{{ $fornecedor->id }}">{{ $fornecedor->name }}</option>
                                @endforeach
                            </select>
                            <p x-show="!detail.row.fornecedor_id && detail.row.supplier_name" class="mt-1 text-[11px] text-steel">
                                Na planilha importada: <span class="text-brand-ink" x-text="detail.row.supplier_name"></span>
                            </p>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="cd-authorizer">Autorizado por</label>
                            <select id="cd-authorizer" x-model="detail.row.authorized_by" @change="save(detail.row)" :disabled="readonly" class="{{ $fieldClass }}">
                                <option value="">Ninguém informado</option>
                                @foreach ($usuarios as $usuario)
                                    <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                                @endforeach
                            </select>
                            <p x-show="!detail.row.authorized_by && detail.row.authorized_by_name" class="mt-1 text-[11px] text-steel">
                                Na planilha importada: <span class="text-brand-ink" x-text="detail.row.authorized_by_name"></span>
                            </p>
                        </div>
                    </section>

                    {{-- Valores: os campos que formam os totais --}}
                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-brand-ink">Valores</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $labelClass }}" for="cd-daily">Diárias</label>
                                <input id="cd-daily" type="text" inputmode="decimal" x-model="detail.row.daily_count" @input="touch(detail.row)" :disabled="readonly"
                                       class="{{ $fieldClass }} text-right tabular-nums">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}" for="cd-qty">Quantidade</label>
                                <input id="cd-qty" type="text" inputmode="decimal" x-model="detail.row.quantity" @input="touch(detail.row)" :disabled="readonly"
                                       class="{{ $fieldClass }} text-right tabular-nums">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $labelClass }}" for="cd-est1">Valor unitário previsto</label>
                                <input id="cd-est1" type="text" inputmode="decimal" :value="detail.row.unit_estimated_1"
                                       @input="detail.row.unit_estimated_1 = maskMoneyDigits($event.target.value); touch(detail.row)" :disabled="readonly" placeholder="0,00"
                                       class="{{ $fieldClass }} text-right tabular-nums">
                                <p class="mt-1 text-right text-[11px] text-steel" x-text="`Total ${brl(detail.row.total_estimated_1)}`"></p>
                            </div>
                            @if ($sheet->uses_second_estimate)
                                <div>
                                    <label class="{{ $labelClass }}" for="cd-est2">Valor unitário previsto 2</label>
                                    <input id="cd-est2" type="text" inputmode="decimal" :value="detail.row.unit_estimated_2"
                                           @input="detail.row.unit_estimated_2 = maskMoneyDigits($event.target.value); touch(detail.row)" :disabled="readonly"
                                           placeholder="Sem 2ª estimativa" class="{{ $fieldClass }} text-right tabular-nums">
                                    <p class="mt-1 text-right text-[11px] text-steel"
                                       x-text="detail.row.unit_estimated_2 === null || detail.row.unit_estimated_2 === '' ? '' : `Total ${brl(detail.row.total_estimated_2)}`"></p>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="cd-actual">Valor unitário realizado</label>
                            <input id="cd-actual" type="text" inputmode="decimal" :value="detail.row.unit_actual"
                                   @input="detail.row.unit_actual = maskMoneyDigits($event.target.value); touch(detail.row)"
                                   :disabled="readonly" placeholder="Não realizado" class="{{ $fieldClass }} text-right tabular-nums">
                            <p class="mt-1 text-right text-[11px] text-steel"
                               x-text="detail.row.unit_actual === '' || detail.row.unit_actual === null ? '' : `Total ${brl(detail.row.total_actual)}`"></p>
                        </div>
                    </section>

                    {{-- Situação: onde a linha está no processo --}}
                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-brand-ink">Situação</h3>
                        <div>
                            <label class="{{ $labelClass }}" for="cd-status">Status</label>
                            <select id="cd-status" x-model="detail.row.status" @change="save(detail.row)" :disabled="readonly" class="{{ $fieldClass }}">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p x-show="detail.row.status_auto" class="mt-1 text-[11px] text-steel">Atualizado sozinho conforme os documentos, até ser trocado à mão.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="{{ $labelClass }}" for="cd-art">ART</label>
                                <select id="cd-art" x-model="detail.row.art_status" @change="save(detail.row)" :disabled="readonly" class="{{ $fieldClass }}">
                                    @foreach ($artStatuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClass }}" for="cd-due">Vencimento</label>
                                <input id="cd-due" type="date" x-model="detail.row.due_date" @change="save(detail.row)" :disabled="readonly"
                                       class="mt-1 w-full rounded-md text-sm focus:border-brand-orange focus:ring-brand-orange"
                                       :class="isDueHighlighted(detail.row.due_date) ? 'border-red-400 bg-red-50 text-red-600 font-medium' : 'border-gray-300'">
                            </div>
                        </div>
                    </section>

                    {{-- Documentos (o antigo "Controle") e pagamentos: abrem o painel lateral --}}
                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-brand-ink">Documentos e pagamentos</h3>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="chip in detail.row.documents.filter(c => c.proof || c.count > 0)" :key="chip.kind">
                                <button type="button" @click="detailToDocuments(chip.kind)"
                                        class="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs transition-colors"
                                        :class="chip.count > 0 ? 'border-green-200 bg-green-50 text-green-700 hover:border-green-400' : 'border-hairline text-steel hover:border-brand-orange hover:text-brand-orange-deep'"
                                        :title="chip.count > 0 ? `Ver ${chip.label}` : `Falta ${chip.label}`">
                                    <i class="fa-solid" :class="chip.icon"></i>
                                    <span x-text="chip.count > 0 ? `${chip.label} (${chip.count})` : chip.label"></span>
                                </button>
                            </template>
                        </div>
                        <button type="button" @click="detailToPayments()"
                                class="flex w-full items-center gap-3 rounded-md border border-hairline px-3 py-2 text-left hover:border-brand-orange">
                            <i class="fa-solid fa-wallet text-steel"></i>
                            <span class="flex-1 text-sm text-brand-ink"
                                  x-text="detail.row.payments_count === 0 ? 'Nenhum pagamento lançado' : `${detail.row.payments_count} pagamento(s) lançado(s)`"></span>
                            <span class="text-xs font-medium text-brand-orange-deep">{{ $sheet->isClosed() ? 'Ver' : 'Ver e lançar' }}</span>
                        </button>
                    </section>

                    <section class="sm:col-span-2">
                        <label class="text-sm font-semibold text-brand-ink" for="cd-notes">Observações</label>
                        <textarea id="cd-notes" rows="3" maxlength="2000" x-model="detail.row.notes" @input="touch(detail.row)" :disabled="readonly"
                                  placeholder="Combinados, número do pedido, qualquer coisa que explique esta despesa."
                                  class="{{ $fieldClass }}"></textarea>
                    </section>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-between gap-3 border-t border-hairline px-5 py-3 sm:px-6">
                <p class="text-xs text-steel">{{ $sheet->isClosed() ? 'Prestação de contas fechada: só leitura.' : 'As alterações são salvas sozinhas e aparecem na grade.' }}</p>
                <div class="flex items-center gap-2">
                    @unless ($sheet->isClosed())
                        <button type="button" @click="duplicate(detail.row)"
                                class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                            <i class="fa-solid fa-copy"></i> Duplicar
                        </button>
                    @endunless
                    <button type="button" @click="closeDetail()"
                            class="rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">Fechar</button>
                </div>
            </div>
        </div>
    </template>
</div>
