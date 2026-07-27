{{-- Painel lateral do requisito (specs/21 §9.6): trecho do edital, documento vinculado, override
     manual e edição do requisito. Toda ação aqui recalcula a aptidão na hora, sem custo de IA. --}}
<div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>

    <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-white shadow-xl flex flex-col"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">

        {{-- Cabeçalho --}}
        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-hairline">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wide text-steel" x-text="requirement?.kind"></p>
                <h3 class="text-base font-semibold text-brand-ink" x-text="requirement?.name"></h3>
                <p class="text-xs text-steel mt-0.5">
                    <span x-text="requirement?.mandatory ? 'Obrigatório' : 'Opcional'"></span>
                    <template x-if="requirement?.typeName">
                        <span> &middot; tipo: <span x-text="requirement.typeName"></span></span>
                    </template>
                </p>
            </div>
            <button type="button" @click="close()" class="text-steel hover:text-brand-ink shrink-0">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            {{-- Empresa em foco + situação --}}
            <div class="rounded-lg border border-hairline p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-brand-ink" x-text="company?.name"></p>
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium" :class="match?.classes">
                        <i class="fa-solid" :class="match?.icon"></i>
                        <span x-text="match?.statusLabel"></span>
                    </span>
                </div>

                <p class="mt-2 text-sm text-steel" x-text="match?.reason"></p>

                <template x-if="match?.manual">
                    <p class="mt-2 text-xs text-brand-orange-deep">
                        <i class="fa-solid fa-user-pen"></i> Conferência definida manualmente — o recálculo automático não a sobrescreve.
                    </p>
                </template>

                <template x-if="!match?.manual && match?.confidence === 'baixa'">
                    <p class="mt-2 text-xs text-amber-700">
                        <i class="fa-solid fa-circle-question"></i>
                        Vínculo por semelhança de nome. Confirme abaixo para transformar em decisão registrada.
                    </p>
                </template>

                <template x-if="match?.documentUrl">
                    <a :href="match.documentUrl" target="_blank" rel="noopener noreferrer"
                       class="mt-3 inline-flex items-center gap-2 text-sm text-brand-orange-deep hover:underline">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <span x-text="match.documentName"></span>
                    </a>
                </template>

                {{-- Troca de empresa sem fechar o painel --}}
                <div class="mt-4 flex flex-wrap gap-1">
                    <template x-for="(item, id) in companies" :key="id">
                        <button type="button" @click="panelCompanyId = Number(id)"
                                :class="Number(id) === panelCompanyId ? 'bg-brand-ink text-white' : 'border border-hairline text-brand-ink hover:bg-surface'"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors" x-text="item.name"></button>
                    </template>
                </div>
            </div>

            {{-- Trecho do edital: a rastreabilidade que sustenta a decisão --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-steel mb-1">
                    Trecho do edital
                    <template x-if="requirement?.page">
                        <span class="font-normal">(página <span x-text="requirement.page"></span>)</span>
                    </template>
                </p>
                <blockquote class="rounded-lg bg-surface border-l-4 border-brand-orange px-4 py-3 text-sm text-brand-ink italic"
                            x-text="requirement?.excerpt"></blockquote>
                <template x-if="requirement?.description">
                    <p class="mt-2 text-xs text-steel" x-text="requirement.description"></p>
                </template>
            </div>

            {{-- Override manual --}}
            <div class="rounded-lg border border-hairline p-4">
                <p class="text-sm font-semibold text-brand-ink mb-3">Ajustar conferência</p>

                <form :action="match?.updateUrl" method="POST" class="space-y-3">
                    @csrf @method('PUT')

                    <div>
                        <x-input-label value="Situação" />
                        <select name="status" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                            @foreach (\App\Domain\Enums\BidMatchStatus::cases() as $status)
                                <option value="{{ $status->value }}" x-bind:selected="match?.status === '{{ $status->value }}'">
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Documento vinculado" />
                        <select name="bid_document_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                            <option value="">Nenhum</option>
                            <template x-for="doc in companyDocuments" :key="doc.id">
                                <option :value="doc.id" x-text="doc.name" x-bind:selected="match?.documentId === doc.id"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-steel">Somente documentos vigentes desta empresa.</p>
                    </div>

                    <div>
                        <x-input-label value="Observação" />
                        <input type="text" name="reason" maxlength="255" x-bind:value="match?.manual ? match?.reason : ''"
                               placeholder="Por que esta é a situação correta?"
                               class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-3 py-2 text-xs font-semibold text-brand-ink hover:bg-brand-orange-deep">
                            <i class="fa-solid fa-check"></i> Salvar e recalcular
                        </button>
                    </div>
                </form>

                <template x-if="match?.manual">
                    <form :action="match?.resetUrl" method="POST" class="mt-3">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-steel hover:text-brand-ink hover:underline">
                            <i class="fa-solid fa-rotate-left"></i> Voltar ao cálculo automático
                        </button>
                    </form>
                </template>
            </div>

            {{-- Edição do requisito extraído --}}
            <div class="rounded-lg border border-hairline p-4">
                <button type="button" @click="editingRequirement = !editingRequirement"
                        class="flex w-full items-center justify-between text-sm font-semibold text-brand-ink">
                    <span>Corrigir ou dispensar este requisito</span>
                    <i class="fa-solid" :class="editingRequirement ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>

                <form x-show="editingRequirement" x-cloak :action="requirement?.updateUrl" method="POST" class="mt-4 space-y-3">
                    @csrf @method('PUT')

                    <div>
                        <x-input-label value="Nome do requisito" />
                        <input type="text" name="name" maxlength="200" required x-bind:value="requirement?.name"
                               class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                    </div>

                    <div>
                        <x-input-label value="Natureza" />
                        <select name="kind" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                            @foreach (\App\Domain\Enums\BidRequirementKind::cases() as $kind)
                                <option value="{{ $kind->value }}" x-bind:selected="requirement?.kindValue === '{{ $kind->value }}'">
                                    {{ $kind->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-brand-ink">
                        <input type="hidden" name="mandatory" value="0">
                        <input type="checkbox" name="mandatory" value="1" x-bind:checked="requirement?.mandatory"
                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        Requisito obrigatório
                    </label>

                    <label class="flex items-center gap-2 text-sm text-brand-ink">
                        <input type="hidden" name="ignored" value="0">
                        <input type="checkbox" name="ignored" value="1" x-bind:checked="requirement?.ignored" x-ref="ignored"
                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        Não se aplica às nossas empresas
                    </label>

                    <div>
                        <x-input-label value="Justificativa (ao marcar como não aplicável)" />
                        <input type="text" name="ignored_reason" maxlength="255" x-bind:value="requirement?.ignoredReason"
                               class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                    </div>

                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-xs font-semibold text-brand-ink hover:bg-surface">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar requisito
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
