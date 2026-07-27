{{-- Correção manual dos dados extraídos do edital (specs/21 §9.6). Mexer no valor estimado
     recalcula os requisitos percentuais (capital/patrimônio mínimos). --}}
<div x-data="{ open: false }" @open-notice-edit.window="open = true">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>

        <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl">
                <form method="POST" action="{{ route('bid.notices.update', $notice) }}" class="divide-y divide-hairline">
                    @csrf @method('PUT')

                    <div class="flex items-center justify-between px-6 py-4">
                        <h3 class="text-base font-semibold text-brand-ink">Corrigir dados do edital</h3>
                        <button type="button" @click="open = false" class="text-steel hover:text-brand-ink">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="sm:col-span-2">
                            <x-input-label for="notice-title" value="Título" />
                            <x-text-input id="notice-title" name="title" class="mt-1" required :value="old('title', $notice->title)" />
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="notice-agency" value="Órgão" />
                            <x-text-input id="notice-agency" name="agency" class="mt-1" :value="old('agency', $notice->agency)" />
                        </div>
                        <div>
                            <x-input-label for="notice-number" value="Número do edital" />
                            <x-text-input id="notice-number" name="number" class="mt-1" :value="old('number', $notice->number)" />
                        </div>
                        <div>
                            <x-input-label for="notice-process" value="Processo" />
                            <x-text-input id="notice-process" name="process_number" class="mt-1" :value="old('process_number', $notice->process_number)" />
                        </div>
                        <div>
                            <x-input-label for="notice-modality" value="Modalidade" />
                            <x-text-input id="notice-modality" name="modality" class="mt-1" :value="old('modality', $notice->modality)" />
                        </div>
                        <div>
                            <x-input-label for="notice-portal" value="Portal" />
                            <x-text-input id="notice-portal" name="portal" class="mt-1" :value="old('portal', $notice->portal)" />
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <x-input-label for="notice-city" value="Cidade" />
                                <x-text-input id="notice-city" name="city" class="mt-1" :value="old('city', $notice->city)" />
                            </div>
                            <div>
                                <x-input-label for="notice-uf" value="UF" />
                                <x-text-input id="notice-uf" name="uf" class="mt-1" x-mask="aa" maxlength="2" :value="old('uf', $notice->uf)" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="notice-value" value="Valor estimado" />
                            <x-form.money name="estimated_value" class="mt-1"
                                          :value="old('estimated_value', $notice->estimated_value !== null ? number_format((float) $notice->estimated_value, 2, ',', '.') : '')" />
                            <p class="mt-1 text-xs text-steel">Base dos requisitos em percentual.</p>
                        </div>
                        <div>
                            <x-input-label for="notice-session" value="Sessão" />
                            <x-text-input id="notice-session" type="datetime-local" name="session_at" class="mt-1"
                                          :value="old('session_at', $notice->session_at?->format('Y-m-d\TH:i'))" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="notice-object" value="Objeto" />
                            <textarea id="notice-object" name="object_summary" rows="3"
                                      class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('object_summary', $notice->object_summary) }}</textarea>
                            <p class="mt-1 text-xs text-steel">
                                O objeto alimenta o desempate por afinidade de ramo das empresas.
                            </p>
                        </div>
                        <div class="sm:col-span-2 space-y-2">
                            <label class="flex items-center gap-2 text-sm text-brand-ink">
                                <input type="hidden" name="me_epp_exclusive" value="0">
                                <input type="checkbox" name="me_epp_exclusive" value="1" @checked(old('me_epp_exclusive', $notice->me_epp_exclusive))
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                Item exclusivo para ME/EPP
                            </label>
                            <label class="flex items-center gap-2 text-sm text-brand-ink">
                                <input type="hidden" name="requires_site_visit" value="0">
                                <input type="checkbox" name="requires_site_visit" value="1" @checked(old('requires_site_visit', $notice->requires_site_visit))
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                Exige visita técnica
                            </label>
                            <label class="flex items-center gap-2 text-sm text-brand-ink">
                                <input type="hidden" name="requires_bid_bond" value="0">
                                <input type="checkbox" name="requires_bid_bond" value="1" @checked(old('requires_bid_bond', $notice->requires_bid_bond))
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                Exige garantia de proposta
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-6 py-4">
                        <button type="button" @click="open = false"
                                class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar e recalcular
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
