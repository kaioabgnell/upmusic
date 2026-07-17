<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Categorias de fornecedor</h2></x-slot>

    <x-page-header title="Categorias de fornecedor" subtitle="Categorias usadas para classificar os fornecedores." icon="fa-tags">
        <x-slot name="actions">
            <a href="{{ route('fornecedores.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
            <a href="{{ route('fornecedor-categorias.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Nova categoria
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome" class="pl-9" />
        </div>
        <x-form.select name="status" class="sm:w-40" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($categorias->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-tags" title="Nenhuma categoria encontrada" message="Cadastre a primeira categoria." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Nome</th>
                <th class="px-4 py-3 font-medium">Unidade</th>
                <th class="px-4 py-3 font-medium">Fornecedores</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($categorias as $categoria)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3 font-medium text-brand-ink">{{ $categoria->nome }}</td>
                    <td class="px-4 py-3 text-steel">{{ $categoria->unidade?->label() ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge variant="neutral">{{ $categoria->fornecedores_count }}</x-badge></td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$categoria->active ? 'success' : 'danger'">{{ $categoria->active ? 'Ativo' : 'Inativo' }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('fornecedor-categorias.edit', $categoria) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('fornecedor-categorias.destroy', $categoria) }}"
                                  data-confirm="Excluir a categoria {{ $categoria->nome }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $categorias->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
