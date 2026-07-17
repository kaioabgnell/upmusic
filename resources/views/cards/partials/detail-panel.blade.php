{{-- Slide-over de detalhe (somente leitura + reabrir/enviar) da listagem global de cards. --}}
<div x-show="panelOpen" x-cloak class="fixed inset-0 z-40" x-transition.opacity>
    <div class="absolute inset-0 bg-black/40" @click="closePanel()"></div>

    <aside class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-xl flex flex-col"
           x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
        <div class="flex items-center justify-between px-5 h-16 border-b border-hairline shrink-0">
            <h3 class="font-semibold text-brand-ink truncate" x-text="card?.title || 'Card'"></h3>
            <button type="button" @click="closePanel()" class="text-steel hover:text-brand-ink"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <div x-show="loading" class="flex-1 flex items-center justify-center text-steel">
            <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
        </div>

        <template x-if="!loading && card">
            <div class="flex-1 overflow-y-auto p-5 space-y-5">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-medium px-2 py-1 rounded-full bg-surface text-steel"><i class="fa-solid fa-table-columns mr-1"></i><span x-text="card.board_name"></span></span>
                    <template x-if="!card.concluded_at">
                        <span class="text-xs font-medium px-2 py-1 rounded-full bg-brand-orange/15 text-brand-orange-deep" x-text="card.column_name"></span>
                    </template>
                    <template x-if="card.concluded_at">
                        <span class="text-xs font-medium px-2 py-1 rounded-full bg-brand-ink text-white"><i class="fa-solid fa-circle-check mr-1"></i>Concluído</span>
                    </template>
                </div>

                <template x-if="card.concluded_at">
                    <p class="text-xs text-steel">Concluído em <span x-text="card.concluded_at"></span><template x-if="card.concluded_by"> por <span x-text="card.concluded_by"></span></template></p>
                </template>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-steel mb-1">Descrição</p>
                    <p class="text-sm text-brand-ink whitespace-pre-line" x-text="card.description || '—'"></p>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><p class="text-xs text-steel">Empresa</p><p class="text-brand-ink" x-text="card.empresa || '—'"></p></div>
                    <div><p class="text-xs text-steel">Evento</p><p class="text-brand-ink" x-text="card.event || '—'"></p></div>
                    <div><p class="text-xs text-steel">Responsável</p><p class="text-brand-ink" x-text="card.assignee || '—'"></p></div>
                    <div><p class="text-xs text-steel">Prazo</p><p class="text-brand-ink" x-text="card.due_date || '—'"></p></div>
                    <div><p class="text-xs text-steel">Prioridade</p><p class="text-brand-ink capitalize" x-text="card.priority"></p></div>
                    <div><p class="text-xs text-steel">Valor previsto</p><p class="text-brand-ink" x-text="card.estimated_value ? 'R$ ' + Number(card.estimated_value).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '—'"></p></div>
                    <div><p class="text-xs text-steel">Valor realizado</p><p class="text-brand-ink" x-text="card.actual_value ? 'R$ ' + Number(card.actual_value).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '—'"></p></div>
                </div>

                <template x-if="card.board_fields && card.board_fields.length">
                    <div class="border-t border-hairline pt-4 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-steel">Campos do quadro</p>
                        <template x-for="f in card.board_fields" :key="f.id">
                            <div class="text-sm">
                                <p class="text-xs text-steel" x-text="f.label"></p>
                                <p class="text-brand-ink" x-text="fieldDisplay(f.id, card.field_values[f.id])"></p>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Ações: enviar para outro quadro (ativo) ou reabrir (concluído) --}}
                <div class="border-t border-hairline pt-4 rounded-lg bg-brand-orange/5 p-3 -mx-1">
                    <template x-if="card.concluded_at">
                        <div>
                            <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-rotate-left text-brand-orange mr-1"></i> Reabrir e enviar para um quadro</p>
                            <div class="flex gap-2">
                                <select x-model="actionBoardId" class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    <option value="">— Selecione o quadro —</option>
                                    @foreach ($boards as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="doReopen()" :disabled="!actionBoardId" class="rounded-md bg-brand-orange px-3 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-40">Reabrir</button>
                            </div>
                        </div>
                    </template>
                    <template x-if="!card.concluded_at">
                        <div>
                            <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-arrow-right-arrow-left text-brand-orange mr-1"></i> Enviar para outro quadro</p>
                            <div class="flex gap-2">
                                <select x-model="actionBoardId" class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    <option value="">— Selecione o quadro —</option>
                                    @foreach ($boards as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="doSendToBoard()" :disabled="!actionBoardId" class="rounded-md bg-brand-orange px-3 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-40">Enviar</button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Anexos --}}
                <div class="border-t border-hairline pt-4">
                    <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-paperclip text-steel mr-1"></i> Anexos</p>
                    <div class="space-y-2">
                        <template x-for="a in attachments" :key="a.id">
                            <div class="flex items-center gap-2 rounded-md border border-hairline p-2 text-sm">
                                <i class="fa-solid fa-file text-steel"></i>
                                <a :href="a.url" class="flex-1 text-brand-ink hover:underline truncate" x-text="a.original_name"></a>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600" x-text="a.kind_label"></span>
                                <button type="button" @click="deleteAttachment(a)" class="text-steel hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
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

                {{-- Comentários --}}
                <div class="border-t border-hairline pt-4">
                    <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-regular fa-comment text-steel mr-1"></i> Comentários</p>
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

                {{-- Histórico --}}
                <div class="border-t border-hairline pt-4">
                    <p class="text-sm font-semibold text-brand-ink mb-2"><i class="fa-solid fa-clock-rotate-left text-steel mr-1"></i> Histórico</p>
                    <div class="space-y-2">
                        <template x-for="(m, i) in movements" :key="i">
                            <div class="flex items-start gap-2 text-xs text-steel">
                                <i class="fa-solid fa-circle text-[6px] mt-1.5 text-brand-orange"></i>
                                <div>
                                    <span class="text-brand-ink" x-text="m.type_label"></span>
                                    <template x-if="m.from"><span> — <span x-text="m.from"></span> <i class="fa-solid fa-arrow-right text-[10px]"></i> <span x-text="m.to"></span></span></template>
                                    <template x-if="!m.from && m.to"><span> — <span x-text="m.to"></span></span></template>
                                    <div><span x-text="m.user"></span> · <span x-text="m.created_at"></span></div>
                                </div>
                            </div>
                        </template>
                        <p x-show="movements.length === 0" class="text-xs text-steel">Sem movimentações.</p>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="!loading && card" class="flex items-center justify-end gap-2 px-5 py-4 border-t border-hairline shrink-0">
            <button type="button" @click="closePanel()" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Fechar</button>
        </div>
    </aside>
</div>
