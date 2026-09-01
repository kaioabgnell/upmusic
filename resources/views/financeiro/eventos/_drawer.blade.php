{{-- Painel lateral da linha de custo: CONTROLE (documentos) e PAGAMENTOS.
     Faz parte do escopo x-data="financeCosts(...)" da tela de Custos. --}}
<div x-show="drawer.open" x-cloak class="fixed inset-0 z-50 flex justify-end" x-transition.opacity>
    <div class="absolute inset-0 bg-black/40" @click="drawer.open = false"></div>

    <div class="relative w-full max-w-md bg-white h-full shadow-xl flex flex-col"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">

        <div class="px-5 py-4 border-b border-hairline shrink-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-ink truncate" x-text="drawer.row?.description"></p>
                    <p class="text-xs text-steel mt-0.5">
                        <span x-text="drawer.row?.categoria ?? 'Sem categoria'"></span>
                        <template x-if="drawer.row?.card_id">
                            <span> · card #<span x-text="drawer.row.card_id"></span></span>
                        </template>
                    </p>
                </div>
                <button type="button" @click="drawer.open = false" class="text-steel hover:text-brand-ink shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="mt-3 flex items-center gap-1">
                <button type="button" @click="drawer.tab = 'documentos'; openDocuments(drawer.row)"
                        class="px-3 py-1.5 text-xs font-medium rounded-md"
                        :class="drawer.tab === 'documentos' ? 'bg-brand-ink text-white' : 'text-steel hover:bg-surface'">
                    <i class="fa-solid fa-folder-open mr-1"></i> Controle
                </button>
                <button type="button" @click="drawer.tab = 'pagamentos'; openPayments(drawer.row)"
                        class="px-3 py-1.5 text-xs font-medium rounded-md"
                        :class="drawer.tab === 'pagamentos' ? 'bg-brand-ink text-white' : 'text-steel hover:bg-surface'">
                    <i class="fa-solid fa-money-bill-transfer mr-1"></i> Pagamentos
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div x-show="drawer.loading" class="py-10 text-center text-steel">
                <i class="fa-solid fa-circle-notch fa-spin text-xl"></i>
            </div>

            {{-- ---- Documentos (o bloco CONTROLE da planilha) ---- --}}
            <div x-show="!drawer.loading && drawer.tab === 'documentos'" class="space-y-4">
                <div class="space-y-2">
                    <template x-for="doc in drawer.documents" :key="doc.id">
                        <div class="rounded-md border border-hairline p-2.5">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-brand-orange/15 text-brand-orange-deep shrink-0"
                                      x-text="doc.kind_label"></span>
                                <a :href="doc.url" target="_blank" rel="noopener" :title="doc.name"
                                   class="flex-1 text-sm text-brand-ink hover:underline truncate" x-text="doc.name"></a>
                                @unless ($sheet->isClosed())
                                    <button type="button" @click="deleteDocument(doc)" class="text-steel hover:text-red-600 shrink-0">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                @endunless
                            </div>
                            <p class="mt-1 text-[11px] text-steel">
                                <template x-if="doc.from_card">
                                    <span><i class="fa-solid fa-link mr-1"></i>Anexo do card #<span x-text="doc.card_id"></span></span>
                                </template>
                                <template x-if="!doc.from_card">
                                    <span><i class="fa-solid fa-cloud-arrow-up mr-1"></i>Upload direto</span>
                                </template>
                                <span x-show="doc.uploader"> · <span x-text="doc.uploader"></span></span>
                                <span x-show="doc.created_at"> · <span x-text="doc.created_at"></span></span>
                            </p>
                        </div>
                    </template>
                    <p x-show="drawer.documents.length === 0" class="text-xs text-steel">
                        Nenhum documento nesta linha ainda.
                    </p>
                </div>

                {{-- Anexos do card ainda não classificados (geral/minuta e o que sobrou) --}}
                <div x-show="drawer.pending.length > 0" x-cloak class="border-t border-hairline pt-4">
                    <p class="text-xs font-semibold text-brand-ink mb-2">Anexos do card ainda não classificados</p>
                    <div class="space-y-2">
                        <template x-for="p in drawer.pending" :key="p.id">
                            <div class="flex items-center gap-2 rounded-md border border-dashed border-hairline p-2">
                                <i class="fa-solid fa-file text-steel text-xs"></i>
                                <span class="flex-1 truncate text-xs text-brand-ink" x-text="p.name" :title="p.name"></span>
                                <select @change="attachPending(p, $event.target.value); $event.target.value = ''"
                                        :disabled="{{ $sheet->isClosed() ? 'true' : 'false' }}"
                                        class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                    <option value="">Classificar como…</option>
                                    <template x-for="k in drawer.kinds" :key="k.value">
                                        <option :value="k.value" x-text="k.label"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </div>

                @unless ($sheet->isClosed())
                    <div class="border-t border-hairline pt-4">
                        <p class="text-xs font-semibold text-brand-ink mb-2">Anexar arquivo direto no financeiro</p>
                        <div class="flex items-center gap-2">
                            <select x-model="uploadKind"
                                    class="text-xs border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                                <template x-for="k in drawer.kinds" :key="k.value">
                                    <option :value="k.value" x-text="k.label"></option>
                                </template>
                            </select>
                            <label class="inline-flex items-center gap-2 rounded-md border border-dashed border-hairline px-3 py-1.5 text-xs font-medium text-steel cursor-pointer hover:border-brand-orange">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Enviar arquivo
                                <input type="file" class="hidden" @change="uploadDocument($event)">
                            </label>
                        </div>
                        <p class="mt-2 text-[11px] text-steel">
                            Use para guia, boleto ou taxa que nunca passou por um card. O que vem do Kanban já chega
                            aqui sozinho.
                        </p>
                    </div>
                @endunless
            </div>

            {{-- ---- Pagamentos ---- --}}
            <div x-show="!drawer.loading && drawer.tab === 'pagamentos'" class="space-y-4">
                <div class="rounded-md bg-surface border border-hairline p-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-steel">Total realizado</span>
                        <span class="font-medium text-brand-ink" x-text="brl(drawer.row?.total_actual)"></span>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-steel">Pago</span>
                        <span class="font-medium text-brand-ink" x-text="brl(drawer.row?.paid)"></span>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-steel">Falta pagar</span>
                        <span class="font-medium" :class="drawer.row?.pending > 0 ? 'text-brand-orange-deep' : 'text-brand-ink'"
                              x-text="brl(drawer.row?.pending)"></span>
                    </div>
                </div>

                <div class="space-y-2">
                    <template x-for="p in drawer.payments" :key="p.id">
                        <div class="flex items-center gap-2 rounded-md border border-hairline p-2.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-brand-ink"><span x-text="p.source"></span></p>
                                <p class="text-[11px] text-steel">
                                    <span x-text="p.paid_at_label ?? 'sem data'"></span>
                                    <span x-show="p.creator"> · <span x-text="p.creator"></span></span>
                                    <span x-show="p.notes"> · <span x-text="p.notes"></span></span>
                                </p>
                            </div>
                            <span class="text-sm font-medium text-brand-ink shrink-0" x-text="brl(p.amount)"></span>
                            @unless ($sheet->isClosed())
                                <button type="button" @click="deletePayment(p)" class="text-steel hover:text-red-600 shrink-0">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            @endunless
                        </div>
                    </template>
                    <p x-show="drawer.payments.length === 0" class="text-xs text-steel">Nenhum pagamento lançado.</p>
                </div>

                @unless ($sheet->isClosed())
                    <div class="border-t border-hairline pt-4 space-y-2">
                        <p class="text-xs font-semibold text-brand-ink">Lançar pagamento</p>
                        <select x-model="newPayment.finance_payment_source_id"
                                class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            <option value="">Grupo de pagamento</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" inputmode="decimal" x-model="newPayment.amount" placeholder="0,00"
                                   class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            <input type="date" x-model="newPayment.paid_at"
                                   class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        </div>
                        <input type="text" x-model="newPayment.notes" placeholder="Observação (opcional)" maxlength="255"
                               class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        <button type="button" @click="addPayment()"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-plus"></i> Registrar pagamento
                        </button>
                    </div>
                @endunless
            </div>
        </div>
    </div>
</div>
