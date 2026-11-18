<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Centros de custo</h2></x-slot>

    <x-page-header title="Centros de custo" subtitle="Classificam as linhas de custos e receitas do financeiro dos eventos." icon="fa-diagram-project">
        <x-slot name="actions">
            <a href="{{ route('cost-centers.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo centro de custo
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por código ou nome" class="pl-9" />
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

    @if ($costCenters->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            @if (request()->hasAny(['search', 'status']))
                <x-empty-state icon="fa-diagram-project" title="Nenhum centro de custo encontrado" message="Ajuste a busca ou limpe os filtros." />
            @else
                <x-empty-state icon="fa-diagram-project" title="Nenhum centro de custo cadastrado" message="Cadastre o primeiro para classificar custos e receitas." />
            @endif
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium w-32">Código</th>
                <th class="px-4 py-3 font-medium">Centro de custo</th>
                <th class="px-4 py-3 font-medium w-28">Em uso</th>
                <th class="px-4 py-3 font-medium w-28">Status</th>
                <th class="px-4 py-3 font-medium text-right w-28">Ações</th>
            </x-slot>

            @foreach ($costCenters as $costCenter)
                @php($usage = $costCenter->cost_items_count + $costCenter->revenues_count)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3 font-medium text-brand-ink whitespace-nowrap">{{ $costCenter->code ?: '—' }}</td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-brand-ink">{{ $costCenter->name }}</p>
                        @if ($costCenter->description)
                            <p class="text-xs text-steel mt-0.5">{{ Str::limit($costCenter->description, 90) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span title="{{ $costCenter->cost_items_count }} custo(s), {{ $costCenter->revenues_count }} receita(s)">
                            <x-badge variant="neutral">{{ $usage }}</x-badge>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$costCenter->active ? 'success' : 'danger'">{{ $costCenter->active ? 'Ativo' : 'Inativo' }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('cost-centers.edit', $costCenter) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            @if ($usage > 0)
                                {{-- Em uso não pode ser excluído (specs/25): o botão fica visível, mas explica o porquê. --}}
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel/40 cursor-not-allowed"
                                      title="Em uso em {{ $usage }} linha(s). Inative em vez de excluir.">
                                    <i class="fa-solid fa-trash"></i>
                                </span>
                            @else
                                <form method="POST" action="{{ route('cost-centers.destroy', $costCenter) }}"
                                      data-confirm="Excluir o centro de custo {{ $costCenter->label }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $costCenters->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
