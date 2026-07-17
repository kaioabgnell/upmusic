@php
    $isEdit = isset($fornecedor);
    $type = old('type', $isEdit ? $fornecedor->type->value : 'PJ');
    $docValue = old('document', $isEdit
        ? ($fornecedor->type->value === 'PF' ? \App\Support\Br::formatCpf($fornecedor->document) : \App\Support\Br::formatCnpj($fornecedor->document))
        : '');
    $categoriaId = old('fornecedor_categoria_id', $isEdit ? $fornecedor->fornecedor_categoria_id : '');
@endphp

<form method="POST" action="{{ $isEdit ? route('fornecedores.update', $fornecedor) : route('fornecedores.store') }}"
      x-data="fornecedorForm('{{ $type }}', @js($categorias), {{ $categoriaId ?: 'null' }}, '{{ route('fornecedor-categorias.quick') }}')"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="type" value="Tipo" />
            <x-form.select id="type" name="type" class="mt-1" x-model="type">
                <option value="PJ" @selected($type === 'PJ')>Pessoa Jurídica</option>
                <option value="PF" @selected($type === 'PF')>Pessoa Física</option>
            </x-form.select>
            <x-input-error :messages="$errors->get('type')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="name" x-text="type === 'PF' ? 'Nome' : 'Razão social'" />
            <x-text-input id="name" name="name" :value="old('name', $isEdit ? $fornecedor->name : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="document" x-text="type === 'PF' ? 'CPF' : 'CNPJ'" />
            <x-text-input id="document" name="document" :value="$docValue" class="mt-1"
                          x-mask:dynamic="type === 'PF' ? '999.999.999-99' : '99.999.999/9999-99'"
                          x-bind:placeholder="type === 'PF' ? '000.000.000-00' : '00.000.000/0000-00'" required />
            <x-input-error :messages="$errors->get('document')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="fornecedor_categoria_id" value="Categoria" />
            <div class="relative mt-1" @click.outside="categoriaOpen = false">
                <button type="button" @click="categoriaOpen = !categoriaOpen; categoriaSearch = ''" class="w-full h-9 flex items-center justify-between gap-2 rounded-md border border-gray-300 px-3 text-sm text-left hover:border-brand-orange transition-colors">
                    <span class="truncate" :class="selectedCategoria ? 'text-brand-ink' : 'text-steel'" x-text="selectedCategoria ? selectedCategoria.nome : '— Selecione —'"></span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-steel shrink-0"></i>
                </button>
                <input type="hidden" id="fornecedor_categoria_id" name="fornecedor_categoria_id" x-model="categoriaId">
                <div x-show="categoriaOpen" x-cloak
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="absolute left-0 z-30 mt-2 w-full bg-white border border-hairline rounded-xl shadow-lg origin-top-left p-2">
                    <div class="relative mb-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                        <input type="text" x-model="categoriaSearch" placeholder="Pesquisar categoria" class="w-full pl-7 h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md">
                    </div>
                    <div class="max-h-48 overflow-y-auto space-y-0.5">
                        <template x-for="c in filteredCategorias" :key="c.id">
                            <button type="button" @click="categoriaId = c.id; categoriaOpen = false" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm text-left">
                                <span class="flex-1 truncate text-brand-ink" x-text="c.nome"></span>
                                <i x-show="Number(categoriaId) === c.id" class="fa-solid fa-check text-brand-orange text-xs"></i>
                            </button>
                        </template>
                        <p x-show="filteredCategorias.length === 0" class="text-xs text-steel px-2 py-1.5">Nenhuma categoria encontrada.</p>
                    </div>
                    <div class="border-t border-hairline pt-1 mt-1">
                        <button type="button" x-show="categoriaId" @click="categoriaId = ''; categoriaOpen = false" class="w-full text-left px-2 py-1.5 text-xs text-red-600 hover:underline">Remover categoria</button>
                        <button type="button" @click="quickCategoria()" class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-surface text-sm text-left text-brand-orange-deep font-medium">
                            <i class="fa-solid fa-plus text-xs"></i> Nova categoria
                        </button>
                    </div>
                </div>
            </div>
            <x-input-error :messages="$errors->get('fornecedor_categoria_id')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email', $isEdit ? $fornecedor->email : '')" class="mt-1" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="phone" value="Telefone" />
            <x-text-input id="phone" name="phone" :value="old('phone', $isEdit ? $fornecedor->phone : '')" class="mt-1"
                          x-mask:dynamic="$input.replace(/\D/g,'').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999'" placeholder="(00) 00000-0000" />
            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Observações" />
        <textarea id="notes" name="notes" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('notes', $isEdit ? $fornecedor->notes : '') }}</textarea>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $isEdit ? $fornecedor->active : true)) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Fornecedor ativo</label>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('fornecedores.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>

@push('scripts')
<script>
    function fornecedorForm(initialType, categorias, initialCategoriaId, quickCategoriaUrl) {
        return {
            type: initialType,
            categorias: categorias,
            categoriaId: initialCategoriaId,
            categoriaOpen: false,
            categoriaSearch: '',
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            get selectedCategoria() {
                return this.categorias.find((c) => c.id === Number(this.categoriaId)) || null;
            },
            get filteredCategorias() {
                const q = this.categoriaSearch.trim().toLowerCase();
                if (!q) return this.categorias;
                return this.categorias.filter((c) => c.nome.toLowerCase().includes(q));
            },
            async quickCategoria() {
                const { value: nome } = await window.Swal.fire({
                    title: 'Nova categoria',
                    customClass: { title: 'up-modal-title' },
                    input: 'text',
                    inputLabel: 'Nome da categoria',
                    inputPlaceholder: 'Ex.: Limpeza',
                    showCancelButton: true,
                    confirmButtonText: 'Cadastrar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ff8c1e',
                    inputValidator: (value) => (!value ? 'Informe um nome.' : undefined),
                });
                if (!nome) return;
                try {
                    const res = await fetch(quickCategoriaUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                        body: JSON.stringify({ nome }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Erro ao cadastrar.');
                    this.categorias.push({ id: data.id, nome: data.nome });
                    this.categoriaId = data.id;
                    this.categoriaOpen = false;
                    window.upAlerts.notifySuccess('Categoria cadastrada.');
                } catch (e) {
                    window.upAlerts.notifyError(e.message);
                }
            },
        };
    }
</script>
@endpush
