{{-- Modal de documento: criar, renovar e editar (specs/21 §9.4).
     Arquivo primeiro → leitura assistida pela IA → campos sugeridos → o usuário confirma.
     Se a IA falhar, o formulário continua utilizável manualmente. --}}
<div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50" @click="open = false"></div>

    <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl" @click.outside="open = false">
            <form :action="action" method="POST" enctype="multipart/form-data" class="divide-y divide-hairline">
                @csrf
                <input type="hidden" name="_method" :value="method">
                <input type="hidden" name="ai_extracted" :value="extracted">
                <input type="hidden" name="ai_confidence" :value="confidence">

                {{-- Cabeçalho --}}
                <div class="flex items-center justify-between px-6 py-4">
                    <h3 class="text-base font-semibold text-brand-ink" x-text="title"></h3>
                    <button type="button" @click="open = false" class="text-steel hover:text-brand-ink">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-5">
                    {{-- 1. Arquivo --}}
                    <div x-show="requiresFile">
                        <x-input-label value="Arquivo do documento" />
                        <label class="mt-1 relative flex flex-col items-center justify-center w-full px-6 py-6 border-2 border-dashed border-hairline rounded-lg cursor-pointer bg-surface hover:border-brand-orange transition-colors">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl text-steel"></i>
                            <span class="mt-2 text-sm text-steel">
                                <span class="font-medium text-brand-ink">Clique para enviar</span> ou arraste o arquivo
                            </span>
                            <span class="mt-1 text-xs text-steel/70">PDF, JPG ou PNG — até {{ (int) (config('licitacoes.document_max_kb') / 1024) }} MB</span>
                            <span x-show="fileName" x-text="fileName" class="mt-2 text-xs font-medium text-brand-orange-deep"></span>
                            <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                   @change="onFileChange($event)" :required="requiresFile">

                            {{-- Estado de leitura: a IA está lendo o arquivo agora. --}}
                            <div x-show="reading" x-cloak
                                 class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-lg bg-white/90">
                                <i class="fa-solid fa-circle-notch fa-spin text-xl text-brand-orange-deep"></i>
                                <span class="text-sm font-medium text-brand-ink">Lendo o documento...</span>
                                <span class="text-xs text-steel">Isso leva alguns segundos.</span>
                            </div>
                        </label>
                        <x-input-error :messages="$errors->get('arquivo')" class="mt-1" />
                    </div>

                    {{-- Avisos da leitura (ex.: CNPJ divergente) — informam, não bloqueiam. --}}
                    <template x-if="warnings.length">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-xs font-semibold text-amber-800 mb-1">
                                <i class="fa-solid fa-triangle-exclamation"></i> Atenção na leitura
                            </p>
                            <ul class="list-disc list-inside text-xs text-amber-800 space-y-0.5">
                                <template x-for="warning in warnings" :key="warning">
                                    <li x-text="warning"></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    {{-- 2. Campos (com selo de sugestão da IA) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between">
                                <x-input-label for="doc-name" value="Nome do documento" />
                                <span x-show="suggested.name" x-cloak class="text-[11px] text-brand-orange-deep">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> sugerido pela IA
                                </span>
                            </div>
                            <x-text-input id="doc-name" name="name" class="mt-1" required
                                          x-model="form.name" @input="touched('name')" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="doc-type" value="Tipo (catálogo)" />
                                <span x-show="suggested.bid_document_type_id" x-cloak class="text-[11px] text-brand-orange-deep">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> sugerido
                                </span>
                            </div>
                            <x-form.select id="doc-type" name="bid_document_type_id" class="mt-1"
                                           x-model="form.bid_document_type_id" @change="typeChanged()">
                                <option value="">Outro (sem tipo canônico)</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </x-form.select>
                            <p class="mt-1 text-xs text-steel">O tipo é o que garante o casamento exato com o edital.</p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="doc-category" value="Categoria" />
                                <span x-show="suggested.bid_document_category_id" x-cloak class="text-[11px] text-brand-orange-deep">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> sugerido
                                </span>
                            </div>
                            <x-form.select id="doc-category" name="bid_document_category_id" class="mt-1" required
                                           x-model="form.bid_document_category_id" @change="touched('bid_document_category_id')">
                                <option value="">Selecione</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </x-form.select>
                            <x-input-error :messages="$errors->get('bid_document_category_id')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="doc-issued" value="Data de emissão" />
                            <x-text-input id="doc-issued" type="date" name="issued_at" class="mt-1"
                                          x-model="form.issued_at" @change="touched('issued_at')" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="doc-expires" value="Data de validade" />
                                <span x-show="suggested.expires_at" x-cloak class="text-[11px] text-brand-orange-deep">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> sugerido
                                </span>
                            </div>
                            <x-text-input id="doc-expires" type="date" name="expires_at" class="mt-1"
                                          x-model="form.expires_at" x-bind:disabled="form.no_expiry"
                                          x-bind:required="!form.no_expiry" @change="touched('expires_at')" />
                            <label class="mt-2 flex items-center gap-2 text-sm text-brand-ink">
                                <input type="hidden" name="no_expiry" value="0">
                                <input type="checkbox" name="no_expiry" value="1" x-model="form.no_expiry"
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                Documento sem validade
                            </label>
                            <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="doc-code" value="Código de controle" />
                                <span x-show="suggested.control_code" x-cloak class="text-[11px] text-brand-orange-deep">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> sugerido
                                </span>
                            </div>
                            <x-text-input id="doc-code" name="control_code" class="mt-1"
                                          x-model="form.control_code" x-bind:required="controlCodeRequired"
                                          @input="touched('control_code')" placeholder="Código de autenticação da certidão" />
                            <x-input-error :messages="$errors->get('control_code')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="doc-issuer" value="Órgão emissor" />
                            <x-text-input id="doc-issuer" name="issuer" class="mt-1"
                                          x-model="form.issuer" @input="touched('issuer')" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="doc-notes" value="Observações" />
                            <textarea id="doc-notes" name="notes" rows="2" x-model="form.notes"
                                      class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm"></textarea>
                        </div>
                    </div>

                    <template x-if="mode === 'renew'">
                        <p class="text-xs text-steel">
                            <i class="fa-solid fa-circle-info"></i>
                            A versão atual vai para o histórico e este arquivo passa a ser o vigente.
                        </p>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-2 px-6 py-4">
                    <button type="button" @click="open = false"
                            class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                        Cancelar
                    </button>
                    <button type="submit" x-bind:disabled="reading"
                            class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors disabled:opacity-50">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
