@php
    $priorityBadge = ['baixa' => 'bg-gray-100 text-gray-600', 'media' => 'bg-brand-orange/15 text-brand-orange-deep', 'alta' => 'bg-red-100 text-red-700'];
    $priorityLabel = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'];
    $boardNames = $boards->keyBy('id');
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Todos os cards</h2></x-slot>

    <div x-data="cardsHub({ cardsBase: '{{ url('cards') }}', anexoBase: '{{ url('anexos') }}' })">
        <x-page-header title="Todos os cards" subtitle="Busca centralizada de cards em todos os quadros, com status e conclusão." icon="fa-layer-group" />

        <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
            <div class="relative flex-1 min-w-[200px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-xs"></i>
                <x-text-input name="search" :value="$filters['search'] ?? ''" placeholder="Buscar por título" class="pl-9 h-9" />
            </div>
            <x-form.select name="empresa_id" class="h-9 sm:w-48" onchange="this.form.submit()">
                <option value="">Empresa</option>
                @foreach ($empresas as $e)
                    <option value="{{ $e->id }}" @selected(($filters['empresa_id'] ?? null) == $e->id)>{{ $e->corporate_name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="board_id" class="h-9 sm:w-44" onchange="this.form.submit()">
                <option value="">Quadro</option>
                @foreach ($boards as $b)
                    <option value="{{ $b->id }}" @selected(($filters['board_id'] ?? null) == $b->id)>{{ $b->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="board_column_id" class="h-9 sm:w-44" onchange="this.form.submit()">
                <option value="">Coluna</option>
                @foreach ($columns as $col)
                    <option value="{{ $col->id }}" @selected(($filters['board_column_id'] ?? null) == $col->id)>
                        @if (empty($filters['board_id'])){{ $boardNames[$col->board_id]->name ?? '' }} — @endif{{ $col->name }}
                    </option>
                @endforeach
            </x-form.select>
            <x-form.select name="status" class="h-9 sm:w-40" onchange="this.form.submit()">
                <option value="">Todos os status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativos</option>
                <option value="concluded" @selected(($filters['status'] ?? '') === 'concluded')>Concluídos</option>
            </x-form.select>
            <button type="submit" class="h-9 rounded-md border border-hairline px-4 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-filter"></i> Filtrar</button>
            @if (array_filter($filters ?? []))
                <a href="{{ route('cards.index') }}" class="h-9 inline-flex items-center px-3 text-sm text-steel hover:text-brand-ink"><i class="fa-solid fa-xmark mr-1"></i>Limpar</a>
            @endif
        </form>

        @if ($cards->isEmpty())
            <div class="bg-white border border-hairline rounded-xl">
                <x-empty-state icon="fa-layer-group" title="Nenhum card encontrado" message="Ajuste os filtros ou aguarde novos cards serem criados." />
            </div>
        @else
            <x-data-table>
                <x-slot name="head">
                    <th class="px-4 py-3 font-medium">Título</th>
                    <th class="px-4 py-3 font-medium">Quadro</th>
                    <th class="px-4 py-3 font-medium">Coluna / Status</th>
                    <th class="px-4 py-3 font-medium">Empresa</th>
                    <th class="px-4 py-3 font-medium">Evento</th>
                    <th class="px-4 py-3 font-medium">Responsável</th>
                    <th class="px-4 py-3 font-medium">Prioridade</th>
                    <th class="px-4 py-3 font-medium">Atualizado em</th>
                    <th class="px-4 py-3 font-medium text-right">Ações</th>
                </x-slot>

                @foreach ($cards as $card)
                    <tr class="hover:bg-surface/60 cursor-pointer" @click="openCard({{ $card->id }})">
                        <td class="px-4 py-3 font-medium text-brand-ink">{{ $card->title }}</td>
                        <td class="px-4 py-3 text-steel">{{ $card->board?->name }}</td>
                        <td class="px-4 py-3">
                            @if ($card->concluded_at)
                                <x-badge variant="dark" icon="fa-circle-check">Concluído</x-badge>
                            @else
                                <x-badge variant="orange">{{ $card->column?->name }}</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-steel">{{ $card->empresa?->corporate_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-steel">{{ $card->event?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-steel">{{ $card->assignee?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $priorityBadge[$card->priority->value] }}">{{ $priorityLabel[$card->priority->value] }}</span></td>
                        <td class="px-4 py-3 text-steel">{{ $card->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('delete', $card)
                                <button type="button" @click.stop="deleteCard({{ $card->id }}, @js($card->title))" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                <x-slot name="footer">{{ $cards->links() }}</x-slot>
            </x-data-table>
        @endif

        @include('cards.partials.detail-panel')
    </div>
</x-app-layout>
