<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Fornecedores</h2></x-slot>

    <x-page-header title="Fornecedores" subtitle="Prestadores PF e PJ contratados no processo." icon="fa-truck-field">
        <x-slot name="actions">
            <a href="{{ route('fornecedor-categorias.index') }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface transition-colors">
                <i class="fa-solid fa-tags"></i> Categorias
            </a>
            <a href="{{ route('fornecedores.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo fornecedor
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome ou documento" class="pl-9" />
        </div>
        <x-form.select name="type" class="sm:w-36" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            <option value="PF" @selected(request('type') === 'PF')>Pessoa Física</option>
            <option value="PJ" @selected(request('type') === 'PJ')>Pessoa Jurídica</option>
        </x-form.select>
        <x-form.select name="status" class="sm:w-40" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($fornecedores->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-truck-field" title="Nenhum fornecedor encontrado" message="Cadastre o primeiro fornecedor." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Nome</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Documento</th>
                <th class="px-4 py-3 font-medium">Categoria</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($fornecedores as $f)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3 font-medium text-brand-ink">{{ $f->name }}</td>
                    <td class="px-4 py-3"><x-badge :variant="$f->type->value === 'PJ' ? 'dark' : 'neutral'">{{ $f->type->value }}</x-badge></td>
                    <td class="px-4 py-3 text-steel">
                        {{ $f->type->value === 'PF' ? \App\Support\Br::formatCpf($f->document) : \App\Support\Br::formatCnpj($f->document) }}
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $f->categoria?->nome ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge :variant="$f->active ? 'success' : 'danger'">{{ $f->active ? 'Ativo' : 'Inativo' }}</x-badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('fornecedores.edit', $f) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="{{ route('fornecedores.destroy', $f) }}" data-confirm="Excluir o fornecedor {{ $f->name }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $fornecedores->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
