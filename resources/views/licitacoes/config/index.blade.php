{{-- Configurações do módulo (specs/21 §9.8): categorias, tipos canônicos e ramos de atuação.
     É aqui que a precisão do matcher é afinada — apelidos e palavras-chave são o que casa o
     vocabulário do edital com o do acervo. --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Licitações · Configurações</h2></x-slot>

    <x-page-header title="Configurações do módulo" icon="fa-sliders"
        subtitle="Categorias, catálogo de tipos de documento e ramos de atuação." />

    <div x-data="{ tab: 'tipos', editing: null, creating: null }">
        <div class="border-b border-hairline mb-4 flex items-center gap-1">
            @foreach (['tipos' => 'Tipos de documento', 'categorias' => 'Categorias', 'ramos' => 'Ramos de atuação'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'; editing = null; creating = null"
                        :class="tab === '{{ $key }}' ? 'border-brand-orange text-brand-ink' : 'border-transparent text-steel hover:text-brand-ink'"
                        class="px-4 py-2 -mb-px border-b-2 text-sm font-medium transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ================= TIPOS DE DOCUMENTO ================= --}}
        <div x-show="tab === 'tipos'">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-steel max-w-2xl">
                    Cada tipo é um documento do mundo real. Os <strong>apelidos</strong> são as variações de
                    nome que aparecem nos editais — é o que garante o casamento exato em vez de palpite.
                    Tipos <strong>essenciais</strong> compõem a barra de saúde documental das empresas.
                </p>
                <button type="button" @click="creating = 'tipo'; editing = null"
                        class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep shrink-0">
                    <i class="fa-solid fa-plus"></i> Novo tipo
                </button>
            </div>

            {{-- Formulário de criação --}}
            <div x-show="creating === 'tipo'" x-cloak class="bg-white border border-brand-orange rounded-xl p-5 mb-4">
                <form method="POST" action="{{ route('bid.types.store') }}" class="space-y-4">
                    @csrf
                    @include('licitacoes.config._type-fields', ['type' => null, 'categories' => $categories])
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="creating = null" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                        <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">Criar tipo</button>
                    </div>
                </form>
            </div>

            <x-data-table>
                <x-slot name="head">
                    <th class="px-4 py-3 font-medium">Tipo</th>
                    <th class="px-4 py-3 font-medium">Categoria</th>
                    <th class="px-4 py-3 font-medium">Apelidos</th>
                    <th class="px-4 py-3 font-medium text-center">Validade padrão</th>
                    <th class="px-4 py-3 font-medium text-center">Essencial</th>
                    <th class="px-4 py-3 font-medium text-center">Docs</th>
                    <th class="px-4 py-3 font-medium text-right">Ações</th>
                </x-slot>

                @foreach ($types as $type)
                    <tr class="hover:bg-surface/60">
                        <td class="px-4 py-3">
                            <span class="block font-medium text-brand-ink">{{ $type->name }}</span>
                            <span class="block text-xs text-steel font-mono">{{ $type->slug }}</span>
                        </td>
                        <td class="px-4 py-3 text-steel">{{ $type->category?->name }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-steel">{{ $type->aliases ? implode(' · ', array_slice($type->aliases, 0, 3)) : '—' }}</span>
                            @if ($type->aliases && count($type->aliases) > 3)
                                <span class="text-xs text-steel/70">+{{ count($type->aliases) - 3 }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-steel">{{ $type->default_validity_days ? $type->default_validity_days.'d' : '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($type->essential)<i class="fa-solid fa-star text-brand-orange"></i>@else<span class="text-gray-300">—</span>@endif
                        </td>
                        <td class="px-4 py-3 text-center text-steel">{{ $type->documents_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="editing = editing === 'tipo-{{ $type->id }}' ? null : 'tipo-{{ $type->id }}'"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('bid.types.destroy', $type) }}"
                                      data-confirm="Excluir o tipo {{ $type->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="editing === 'tipo-{{ $type->id }}'" x-cloak>
                        <td colspan="7" class="px-4 py-4 bg-surface/60">
                            <form method="POST" action="{{ route('bid.types.update', $type) }}" class="space-y-4">
                                @csrf @method('PUT')
                                @include('licitacoes.config._type-fields', ['type' => $type, 'categories' => $categories])
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="editing = null" class="rounded-md border border-hairline bg-white px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                                    <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">Salvar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

        {{-- ================= CATEGORIAS ================= --}}
        <div x-show="tab === 'categorias'" x-cloak>
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-steel max-w-2xl">
                    As seis categorias nativas não podem ser excluídas: seus identificadores são o contrato
                    com a IA. Você pode renomeá-las e criar categorias próprias para organização.
                </p>
                <button type="button" @click="creating = 'categoria'; editing = null"
                        class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep shrink-0">
                    <i class="fa-solid fa-plus"></i> Nova categoria
                </button>
            </div>

            <div x-show="creating === 'categoria'" x-cloak class="bg-white border border-brand-orange rounded-xl p-5 mb-4">
                <form method="POST" action="{{ route('bid.categories.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label value="Nome" />
                            <x-text-input name="name" class="mt-1" required />
                        </div>
                        <div>
                            <x-input-label value="Ícone (Font Awesome)" />
                            <x-text-input name="icon" class="mt-1" placeholder="fa-folder-open" />
                        </div>
                        <div>
                            <x-input-label value="Cor" />
                            <input type="color" name="color" value="#5a5a5c" class="mt-1 h-10 w-20 rounded-md border border-hairline bg-white p-1">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-brand-ink">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        Ativa
                    </label>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="creating = null" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                        <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">Criar categoria</button>
                    </div>
                </form>
            </div>

            <x-data-table>
                <x-slot name="head">
                    <th class="px-4 py-3 font-medium">Categoria</th>
                    <th class="px-4 py-3 font-medium">Identificador</th>
                    <th class="px-4 py-3 font-medium text-center">Tipos</th>
                    <th class="px-4 py-3 font-medium text-center">Documentos</th>
                    <th class="px-4 py-3 font-medium text-center">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Ações</th>
                </x-slot>

                @foreach ($categories as $category)
                    <tr class="hover:bg-surface/60">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2 font-medium text-brand-ink">
                                <i class="fa-solid {{ $category->icon ?: 'fa-folder-open' }}" style="color: {{ $category->color }}"></i>
                                {{ $category->name }}
                                @if ($category->system)<x-badge variant="neutral">nativa</x-badge>@endif
                            </span>
                        </td>
                        <td class="px-4 py-3 text-steel font-mono text-xs">{{ $category->slug }}</td>
                        <td class="px-4 py-3 text-center text-steel">{{ $category->types_count }}</td>
                        <td class="px-4 py-3 text-center text-steel">{{ $category->documents_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <x-badge :variant="$category->active ? 'success' : 'danger'">{{ $category->active ? 'Ativa' : 'Inativa' }}</x-badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="editing = editing === 'cat-{{ $category->id }}' ? null : 'cat-{{ $category->id }}'"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                @unless ($category->system)
                                    <form method="POST" action="{{ route('bid.categories.destroy', $category) }}"
                                          data-confirm="Excluir a categoria {{ $category->name }}?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                    <tr x-show="editing === 'cat-{{ $category->id }}'" x-cloak>
                        <td colspan="6" class="px-4 py-4 bg-surface/60">
                            <form method="POST" action="{{ route('bid.categories.update', $category) }}" class="space-y-4">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                    <div class="sm:col-span-2">
                                        <x-input-label value="Nome" />
                                        <x-text-input name="name" class="mt-1" required :value="$category->name" />
                                    </div>
                                    <div>
                                        <x-input-label value="Ícone" />
                                        <x-text-input name="icon" class="mt-1" :value="$category->icon" />
                                    </div>
                                    <div>
                                        <x-input-label value="Cor" />
                                        <input type="color" name="color" value="{{ $category->color }}" class="mt-1 h-10 w-20 rounded-md border border-hairline bg-white p-1">
                                    </div>
                                    @unless ($category->system)
                                        <div>
                                            <x-input-label value="Identificador" />
                                            <x-text-input name="slug" class="mt-1" :value="$category->slug" />
                                        </div>
                                    @endunless
                                    <div>
                                        <x-input-label value="Ordem" />
                                        <x-text-input type="number" name="sort_order" class="mt-1" :value="$category->sort_order" min="0" />
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-brand-ink">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox" name="active" value="1" @checked($category->active)
                                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                    Ativa
                                </label>
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="editing = null" class="rounded-md border border-hairline bg-white px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                                    <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">Salvar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

        {{-- ================= RAMOS ================= --}}
        <div x-show="tab === 'ramos'" x-cloak>
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-steel max-w-2xl">
                    As palavras-chave são procuradas no objeto do edital. Quando batem, a empresa daquele ramo
                    ganha destaque no ranking — é o desempate por vocação.
                </p>
                <button type="button" @click="creating = 'ramo'; editing = null"
                        class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep shrink-0">
                    <i class="fa-solid fa-plus"></i> Novo ramo
                </button>
            </div>

            <div x-show="creating === 'ramo'" x-cloak class="bg-white border border-brand-orange rounded-xl p-5 mb-4">
                <form method="POST" action="{{ route('bid.lines.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label value="Nome do ramo" />
                            <x-text-input name="name" class="mt-1" required />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Palavras-chave" />
                            <x-text-input name="keywords" class="mt-1" placeholder="evento, palco, sonorização" />
                            <p class="mt-1 text-xs text-steel">Separe por vírgula. Acentos e maiúsculas são normalizados.</p>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-brand-ink">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        Ativo
                    </label>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="creating = null" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                        <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">Criar ramo</button>
                    </div>
                </form>
            </div>

            <x-data-table>
                <x-slot name="head">
                    <th class="px-4 py-3 font-medium">Ramo</th>
                    <th class="px-4 py-3 font-medium">Palavras-chave</th>
                    <th class="px-4 py-3 font-medium text-center">Empresas</th>
                    <th class="px-4 py-3 font-medium text-center">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Ações</th>
                </x-slot>

                @foreach ($lines as $line)
                    <tr class="hover:bg-surface/60">
                        <td class="px-4 py-3 font-medium text-brand-ink">{{ $line->name }}</td>
                        <td class="px-4 py-3 text-xs text-steel">{{ $line->keywords ? implode(' · ', $line->keywords) : '—' }}</td>
                        <td class="px-4 py-3 text-center text-steel">{{ $line->companies->count() }}</td>
                        <td class="px-4 py-3 text-center">
                            <x-badge :variant="$line->active ? 'success' : 'danger'">{{ $line->active ? 'Ativo' : 'Inativo' }}</x-badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="editing = editing === 'ramo-{{ $line->id }}' ? null : 'ramo-{{ $line->id }}'"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('bid.lines.destroy', $line) }}"
                                      data-confirm="Excluir o ramo {{ $line->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="editing === 'ramo-{{ $line->id }}'" x-cloak>
                        <td colspan="5" class="px-4 py-4 bg-surface/60">
                            <form method="POST" action="{{ route('bid.lines.update', $line) }}" class="space-y-4">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label value="Nome do ramo" />
                                        <x-text-input name="name" class="mt-1" required :value="$line->name" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <x-input-label value="Palavras-chave" />
                                        <x-text-input name="keywords" class="mt-1" :value="$line->keywords ? implode(', ', $line->keywords) : ''" />
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-brand-ink">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox" name="active" value="1" @checked($line->active)
                                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                    Ativo
                                </label>
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="editing = null" class="rounded-md border border-hairline bg-white px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</button>
                                    <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">Salvar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
