<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Custos fixos</h2></x-slot>

    <x-page-header title="Custos fixos" subtitle="Custos recorrentes da empresa. Cada parcela vira um card no quadro de custos fixos." icon="fa-arrows-rotate">
        <x-slot name="actions">
            <a href="{{ route('fixed-costs.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo custo fixo
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col lg:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome" class="pl-9" />
        </div>
        <x-form.select name="empresa_id" class="lg:w-52" onchange="this.form.submit()">
            <option value="">Todas as empresas</option>
            @foreach ($empresas as $empresa)
                <option value="{{ $empresa->id }}" @selected(request('empresa_id') == $empresa->id)>{{ $empresa->trade_name ?: $empresa->corporate_name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="board_id" class="lg:w-44" onchange="this.form.submit()">
            <option value="">Todos os quadros</option>
            @foreach ($boards as $board)
                <option value="{{ $board->id }}" @selected(request('board_id') == $board->id)>{{ $board->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="generation_mode" class="lg:w-44" onchange="this.form.submit()">
            <option value="">Todos os modos</option>
            <option value="all_upfront" @selected(request('generation_mode') === 'all_upfront')>Todas agora</option>
            <option value="lead_days" @selected(request('generation_mode') === 'lead_days')>X dias antes</option>
        </x-form.select>
        <x-form.select name="status" class="lg:w-36" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($fixedCosts->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            @if (request()->hasAny(['search', 'empresa_id', 'board_id', 'generation_mode', 'status']))
                <x-empty-state icon="fa-arrows-rotate" title="Nenhum custo fixo encontrado" message="Ajuste a busca ou limpe os filtros." />
            @else
                <x-empty-state icon="fa-arrows-rotate" title="Nenhum custo fixo cadastrado" message="Cadastre aluguel, água, parcelamentos e empréstimos para gerar os cards de cada parcela." />
            @endif
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Custo fixo</th>
                <th class="px-4 py-3 font-medium">Empresa</th>
                <th class="px-4 py-3 font-medium text-right">Valor da parcela</th>
                <th class="px-4 py-3 font-medium w-44">Progresso</th>
                <th class="px-4 py-3 font-medium">Próximo vencimento</th>
                <th class="px-4 py-3 font-medium">Modo</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($fixedCosts as $cost)
                @php
                    // Parcelas cobertas: as geradas mais as anteriores a "começar na parcela" (pagas fora do sistema).
                    $covered = max($cost->maxGeneratedNumber(), $cost->start_installment - 1);
                    $percent = $cost->installments_total > 0 ? min(100, round($covered / $cost->installments_total * 100)) : 0;
                    $next = $cost->nextPendingNumber();
                @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <a href="{{ route('fixed-costs.show', $cost) }}" class="font-medium text-brand-ink hover:underline">{{ $cost->name }}</a>
                        <p class="text-xs text-steel mt-0.5"><i class="fa-solid fa-table-columns mr-1"></i>{{ $cost->board?->name ?? '—' }}</p>
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $cost->empresa?->trade_name ?: $cost->empresa?->corporate_name }}</td>
                    <td class="px-4 py-3 text-right font-medium text-brand-ink whitespace-nowrap">{{ \App\Support\Br::formatMoney((float) $cost->installment_value) }}</td>
                    <td class="px-4 py-3">
                        <p class="text-xs text-brand-ink"><span class="font-semibold">{{ $covered }}</span> de {{ $cost->installments_total }} geradas</p>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-brand-ink" style="width: {{ $percent }}%"></div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($next)
                            <span class="text-brand-ink">{{ $cost->dueDateFor($next)->format('d/m/Y') }}</span>
                            <span class="text-xs text-steel">· parcela {{ $next }}</span>
                        @else
                            <span class="text-steel">Todas geradas</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-steel whitespace-nowrap">{{ $cost->modeLabel() }}</td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$cost->active ? 'success' : 'danger'">{{ $cost->active ? 'Ativo' : 'Inativo' }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @include('custos-fixos._actions', ['cost' => $cost, 'compact' => true, 'reactivationCount' => $reactivationCounts[$cost->id] ?? 0])
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $fixedCosts->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
