<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Templates de cards</h2></x-slot>

    <x-page-header title="Templates de cards" subtitle="Conjuntos pré-definidos de cards para importar de uma vez." icon="fa-clone">
        <x-slot name="actions">
            <a href="{{ route('templates.create') }}" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo template
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="mb-4">
        <div class="relative max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar template" class="pl-9" />
        </div>
    </form>

    @if ($templates->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-clone" title="Nenhum template" message="Crie um template para agilizar a criação de cards repetitivos." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Nome</th>
                <th class="px-4 py-3 font-medium">Quadro</th>
                <th class="px-4 py-3 font-medium">Cards</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($templates as $tpl)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <p class="font-medium text-brand-ink">{{ $tpl->name }}</p>
                        @if ($tpl->description)<p class="text-xs text-steel">{{ Str::limit($tpl->description, 60) }}</p>@endif
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $tpl->board?->name ?? '—' }}</td>
                    <td class="px-4 py-3"><x-badge variant="neutral">{{ $tpl->items_count }}</x-badge></td>
                    <td class="px-4 py-3"><x-badge :variant="$tpl->active ? 'success' : 'danger'">{{ $tpl->active ? 'Ativo' : 'Inativo' }}</x-badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @if ($tpl->active && $tpl->items_count > 0)
                                <button type="button" onclick="importTemplate({{ $tpl->id }}, {{ $tpl->board_id ?? 'null' }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-brand-ink px-3 py-1.5 text-sm font-medium text-white hover:bg-black" title="Importar">
                                    <i class="fa-solid fa-file-import"></i> Importar
                                </button>
                            @endif
                            <a href="{{ route('templates.edit', $tpl) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="{{ route('templates.destroy', $tpl) }}" data-confirm="Excluir o template {{ $tpl->name }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $templates->links() }}</x-slot>
        </x-data-table>
    @endif

    @push('scripts')
    <script>
        const TPL_BOARDS = @json($boards);
        const TPL_EMPRESAS = @json($empresas);
        const TPL_CSRF = document.querySelector('meta[name="csrf-token"]').content;

        async function importTemplate(templateId, defaultBoardId) {
            const boardOpts = TPL_BOARDS.map(b => `<option value="${b.id}" ${b.id === defaultBoardId ? 'selected' : ''}>${b.name}</option>`).join('');
            const empresaOpts = ['<option value="">— Sem empresa —</option>']
                .concat(TPL_EMPRESAS.map(e => `<option value="${e.id}">${e.corporate_name}</option>`)).join('');

            const selectClass = 'block w-full h-9 text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md';
            const { value: form } = await window.Swal.fire({
                title: 'Importar template',
                html:
                    `<div class="text-left">
                        <label class="block text-sm font-medium text-brand-ink mb-1">Quadro de destino</label>
                        <select id="imp-board" class="${selectClass}">${boardOpts}</select>
                        <label class="block text-sm font-medium text-brand-ink mb-1 mt-3">Vincular empresa (opcional)</label>
                        <select id="imp-empresa" class="${selectClass}">${empresaOpts}</select>
                    </div>`,
                showCancelButton: true,
                confirmButtonText: 'Importar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ff8c1e',
                preConfirm: () => ({
                    board_id: document.getElementById('imp-board').value,
                    empresa_id: document.getElementById('imp-empresa').value,
                }),
            });
            if (!form) return;

            const f = document.createElement('form');
            f.method = 'POST';
            f.action = `{{ url('templates') }}/${templateId}/importar`;
            f.innerHTML = `<input type="hidden" name="_token" value="${TPL_CSRF}">` +
                `<input type="hidden" name="board_id" value="${form.board_id}">` +
                `<input type="hidden" name="empresa_id" value="${form.empresa_id}">`;
            document.body.appendChild(f);
            f.submit();
        }
    </script>
    @endpush
</x-app-layout>
