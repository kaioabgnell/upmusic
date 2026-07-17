<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Empresas</h2></x-slot>

    <x-page-header title="Empresas" subtitle="Clientes vinculáveis aos cards, financeiro e preços." icon="fa-building">
        <x-slot name="actions">
            @can('create', App\Models\Empresa::class)
                <a href="{{ route('empresas.create') }}"
                   class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                    <i class="fa-solid fa-plus"></i> Nova empresa
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por razão social, fantasia ou documento" class="pl-9" />
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

    @if ($empresas->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-building" title="Nenhuma empresa encontrada" message="Cadastre a primeira empresa cliente." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Razão social</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Documento</th>
                <th class="px-4 py-3 font-medium">Cidade/UF</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($empresas as $empresa)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <p class="font-medium text-brand-ink">{{ $empresa->corporate_name }}</p>
                        @if ($empresa->trade_name)<p class="text-xs text-steel">{{ $empresa->trade_name }}</p>@endif
                    </td>
                    <td class="px-4 py-3"><x-badge :variant="$empresa->type->value === 'PJ' ? 'dark' : 'neutral'">{{ $empresa->type->value }}</x-badge></td>
                    <td class="px-4 py-3 text-steel">
                        {{ $empresa->type->value === 'PF' ? \App\Support\Br::formatCpf($empresa->document) : \App\Support\Br::formatCnpj($empresa->document) }}
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $empresa->city ? $empresa->city.'/'.$empresa->state : '—' }}</td>
                    <td class="px-4 py-3"><x-badge :variant="$empresa->active ? 'success' : 'danger'">{{ $empresa->active ? 'Ativa' : 'Inativa' }}</x-badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $empresa)
                                <a href="{{ route('empresas.edit', $empresa) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                            @can('delete', $empresa)
                                <form method="POST" action="{{ route('empresas.destroy', $empresa) }}" data-confirm="Excluir a empresa {{ $empresa->corporate_name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $empresas->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
