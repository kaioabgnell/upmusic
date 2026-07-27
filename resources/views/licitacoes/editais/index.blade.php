{{-- Lista de análises de edital (specs/21 §9.6). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Licitações · Editais</h2></x-slot>

    <x-page-header title="Análise de editais" icon="fa-file-contract"
        subtitle="Envie o edital e veja qual das suas empresas está mais apta a participar.">
        <x-slot name="actions">
            <a href="{{ route('bid.notices.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Nova análise
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por título, órgão ou número" class="pl-9" />
        </div>
        <x-form.select name="status" class="sm:w-44" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            @foreach (\App\Domain\Enums\BidNoticeStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($notices->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-file-contract" title="Nenhum edital analisado"
                message="Gere o PDF do edital, envie aqui e o sistema aponta a empresa mais apta.">
                <x-slot name="action">
                    <a href="{{ route('bid.notices.create') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Analisar edital
                    </a>
                </x-slot>
            </x-empty-state>
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Edital</th>
                <th class="px-4 py-3 font-medium">Sessão</th>
                <th class="px-4 py-3 font-medium">Valor estimado</th>
                <th class="px-4 py-3 font-medium">Mais apta</th>
                <th class="px-4 py-3 font-medium">Situação</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($notices as $notice)
                @php $top = $notice->evaluations->first(); @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <a href="{{ route('bid.notices.show', $notice) }}" class="block min-w-0 group">
                            <span class="block font-medium text-brand-ink truncate group-hover:underline">{{ $notice->title }}</span>
                            <span class="block text-xs text-steel truncate">
                                {{ $notice->agency ?: 'Órgão não identificado' }}
                                @if ($notice->number) &middot; nº {{ $notice->number }} @endif
                                @if ($notice->modality) &middot; {{ $notice->modality }} @endif
                            </span>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-steel whitespace-nowrap">
                        {{ $notice->session_at?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-steel whitespace-nowrap">
                        {{ $notice->estimated_value !== null ? \App\Support\Br::formatMoney((float) $notice->estimated_value) : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @if ($top)
                            <div class="flex items-center gap-2 min-w-0">
                                <x-badge :variant="$top->verdict->badgeVariant()" :icon="$top->verdict->icon()">
                                    {{ number_format((float) $top->score, 0) }}
                                </x-badge>
                                <span class="text-sm text-brand-ink truncate">{{ $top->company?->display_name }}</span>
                            </div>
                        @else
                            <span class="text-steel text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($notice->is_stale)
                            <x-badge variant="danger" icon="fa-plug-circle-xmark">Interrompida</x-badge>
                        @else
                            <x-badge :variant="$notice->status->badgeVariant()">{{ $notice->status->label() }}</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('bid.notices.show', $notice) }}" title="Abrir análise"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                            <form method="POST" action="{{ route('bid.notices.destroy', $notice) }}"
                                  data-confirm="Excluir a análise do edital {{ $notice->title }}?">
                                @csrf @method('DELETE')
                                <button type="submit" title="Excluir"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">{{ $notices->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
