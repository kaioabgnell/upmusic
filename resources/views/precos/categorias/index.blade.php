<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Banco de preços</h2></x-slot>

    <x-page-header title="Banco de preços" subtitle="Histórico de preços por categoria de fornecedor." icon="fa-tags">
        <x-slot name="actions">
            <a href="{{ route('prices.history') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-chart-line"></i> Evolução de preços</a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar categoria" class="pl-9" />
        </div>
        <x-form.select name="status" class="sm:w-40" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>

    @if ($categorias->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-tags" title="Nenhuma categoria cadastrada" message="Cadastre uma categoria de fornecedor para começar a registrar preços." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Categoria</th>
                <th class="px-4 py-3 font-medium">Unidade</th>
                <th class="px-4 py-3 font-medium">Registros</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($categorias as $categoria)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3 font-medium text-brand-ink">{{ $categoria->nome }}</td>
                    <td class="px-4 py-3 text-steel">{{ $categoria->unidade?->label() ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge variant="neutral">{{ $categoria->price_records_count }}</x-badge></td>
                    <td class="px-4 py-3"><x-badge :variant="$categoria->active ? 'success' : 'danger'">{{ $categoria->active ? 'Ativo' : 'Inativo' }}</x-badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('prices.history', ['categoria_id' => $categoria->id]) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Ver evolução"><i class="fa-solid fa-chart-line"></i></a>
                            <a href="{{ route('prices.categorias.show', $categoria) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Registros de preço"><i class="fa-solid fa-tag"></i></a>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $categorias->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
