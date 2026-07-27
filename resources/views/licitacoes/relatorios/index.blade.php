{{-- Relatórios de dados passados (specs/21 §9.7). O histórico usa o veredito e o score
     CONGELADOS na análise — não os atuais, que mudam a cada renovação de certidão. --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Licitações · Relatórios</h2></x-slot>

    <x-page-header title="Relatórios" icon="fa-chart-column"
        subtitle="Histórico de análises, aptidão por empresa e conformidade documental.">
        <x-slot name="actions">
            <a href="{{ route('bid.reports.export', request()->query()) }}"
               class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-file-csv"></i> Exportar CSV
            </a>
        </x-slot>
    </x-page-header>

    {{-- Filtros --}}
    <form method="GET" class="bg-white border border-hairline rounded-xl p-4 mb-6 grid grid-cols-1 sm:grid-cols-5 gap-3">
        <div>
            <x-input-label for="from" value="De" />
            <x-text-input id="from" type="date" name="from" class="mt-1" :value="request('from')" />
        </div>
        <div>
            <x-input-label for="to" value="Até" />
            <x-text-input id="to" type="date" name="to" class="mt-1" :value="request('to')" />
        </div>
        <div>
            <x-input-label for="company_id" value="Empresa" />
            <x-form.select id="company_id" name="company_id" class="mt-1">
                <option value="">Todas</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->display_name }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div>
            <x-input-label for="category_id" value="Categoria" />
            <x-form.select id="category_id" name="category_id" class="mt-1">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div class="flex items-end gap-2">
            <x-form.select name="verdict" class="flex-1">
                <option value="">Todos os vereditos</option>
                @foreach ($verdicts as $verdict)
                    <option value="{{ $verdict->value }}" @selected(request('verdict') === $verdict->value)>{{ $verdict->label() }}</option>
                @endforeach
            </x-form.select>
            <button type="submit" class="rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                <i class="fa-solid fa-filter"></i>
            </button>
        </div>
    </form>

    {{-- 1. Histórico de análises --}}
    <section class="mb-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-steel mb-3">Histórico de análises</h2>

        @if ($history->isEmpty())
            <div class="bg-white border border-hairline rounded-xl">
                <x-empty-state icon="fa-file-contract" title="Nenhuma análise no período" />
            </div>
        @else
            <x-data-table>
                <x-slot name="head">
                    <th class="px-4 py-3 font-medium">Edital</th>
                    <th class="px-4 py-3 font-medium">Sessão</th>
                    <th class="px-4 py-3 font-medium">Valor estimado</th>
                    <th class="px-4 py-3 font-medium">1ª colocada</th>
                    <th class="px-4 py-3 font-medium">Veredito na análise</th>
                    <th class="px-4 py-3 font-medium text-right">Score</th>
                </x-slot>

                @foreach ($history as $notice)
                    @php $top = $notice->evaluations->first(); @endphp
                    <tr class="hover:bg-surface/60">
                        <td class="px-4 py-3">
                            <a href="{{ route('bid.notices.show', $notice) }}" class="block min-w-0 group">
                                <span class="block font-medium text-brand-ink truncate group-hover:underline">{{ $notice->title }}</span>
                                <span class="block text-xs text-steel truncate">
                                    {{ $notice->agency ?: '—' }} &middot; analisado em {{ $notice->created_at->format('d/m/Y') }}
                                </span>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-steel whitespace-nowrap">{{ $notice->session_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-steel whitespace-nowrap">
                            {{ $notice->estimated_value !== null ? \App\Support\Br::formatMoney((float) $notice->estimated_value) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-brand-ink">{{ $top?->company?->display_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($top?->verdict_at_analysis)
                                <x-badge :variant="$top->verdict_at_analysis->badgeVariant()">{{ $top->verdict_at_analysis->label() }}</x-badge>
                            @else
                                <span class="text-steel text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-brand-ink">
                            {{ $top?->score_at_analysis !== null ? number_format((float) $top->score_at_analysis, 0) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </section>

    <div class="grid lg:grid-cols-2 gap-6 mb-8">
        {{-- 2. Aptidão por empresa --}}
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-steel mb-3">Aptidão por empresa</h2>

            @if ($aptitude->isEmpty())
                <div class="bg-white border border-hairline rounded-xl">
                    <x-empty-state icon="fa-ranking-star" title="Sem dados no período" />
                </div>
            @else
                <x-data-table>
                    <x-slot name="head">
                        <th class="px-4 py-3 font-medium">Empresa</th>
                        <th class="px-4 py-3 font-medium text-center">Aptas</th>
                        <th class="px-4 py-3 font-medium text-center">Pendências</th>
                        <th class="px-4 py-3 font-medium text-center">Inaptas</th>
                        <th class="px-4 py-3 font-medium text-right">Score médio</th>
                    </x-slot>

                    @foreach ($aptitude as $row)
                        <tr class="hover:bg-surface/60">
                            <td class="px-4 py-3 text-brand-ink">
                                <a href="{{ route('bid.companies.show', $row['company']) }}" class="hover:underline">
                                    {{ $row['company']->display_name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center text-green-700 font-medium">{{ $row['aptas'] }}</td>
                            <td class="px-4 py-3 text-center text-amber-700 font-medium">{{ $row['pendencias'] }}</td>
                            <td class="px-4 py-3 text-center text-red-700 font-medium">{{ $row['inaptas'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-brand-ink">{{ number_format($row['media'], 1, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </section>

        {{-- 3. Top motivos de inaptidão --}}
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-steel mb-3">O que mais bloqueou</h2>

            @if ($blockers->isEmpty())
                <div class="bg-white border border-hairline rounded-xl">
                    <x-empty-state icon="fa-ban" title="Nenhum bloqueio registrado" />
                </div>
            @else
                <x-data-table>
                    <x-slot name="head">
                        <th class="px-4 py-3 font-medium">Requisito</th>
                        <th class="px-4 py-3 font-medium">Natureza</th>
                        <th class="px-4 py-3 font-medium text-center">Editais</th>
                        <th class="px-4 py-3 font-medium text-right">Ocorrências</th>
                    </x-slot>

                    @foreach ($blockers as $row)
                        <tr class="hover:bg-surface/60">
                            <td class="px-4 py-3 text-brand-ink">{{ $row->name }}</td>
                            <td class="px-4 py-3 text-steel">
                                {{ \App\Domain\Enums\BidRequirementKind::tryFrom($row->kind)?->label() ?? $row->kind }}
                            </td>
                            <td class="px-4 py-3 text-center text-steel">{{ $row->editais }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-red-700">{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </x-data-table>
                <p class="mt-2 text-xs text-steel">Onde investir em documentação para deixar de perder editais.</p>
            @endif
        </section>
    </div>

    {{-- 4. Conformidade documental --}}
    <section class="mb-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-steel mb-3">Conformidade documental</h2>

        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Empresa</th>
                <th class="px-4 py-3 font-medium text-center">Vencidos hoje</th>
                <th class="px-4 py-3 font-medium text-center">Vencimentos no período</th>
                <th class="px-4 py-3 font-medium text-right">Dias médios até renovar</th>
            </x-slot>

            @foreach ($compliance as $row)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3 text-brand-ink">
                        <a href="{{ route('bid.companies.show', $row['company']) }}" class="hover:underline">
                            {{ $row['company']->display_name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-center {{ $row['vencidos_hoje'] > 0 ? 'text-red-700 font-semibold' : 'text-steel' }}">
                        {{ $row['vencidos_hoje'] }}
                    </td>
                    <td class="px-4 py-3 text-center text-steel">{{ $row['vencimentos_periodo'] }}</td>
                    <td class="px-4 py-3 text-right text-steel">
                        {{ $row['dias_medios'] !== null ? number_format((float) $row['dias_medios'], 1, ',', '.') : '—' }}
                    </td>
                </tr>
            @endforeach
        </x-data-table>
        <p class="mt-2 text-xs text-steel">
            "Vencimentos no período" conta as vezes em que um documento só foi renovado depois de já estar
            vencido — cada linha é uma janela em que a empresa ficou irregular.
        </p>
    </section>

    {{-- 5. Vencimentos futuros --}}
    <section>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-steel mb-3">Vencimentos dos próximos 90 dias</h2>

        @if ($upcoming->isEmpty())
            <div class="bg-white border border-hairline rounded-xl">
                <x-empty-state icon="fa-calendar-check" title="Nenhum vencimento nos próximos 90 dias" />
            </div>
        @else
            <div class="space-y-4">
                @foreach ($upcoming as $month => $documents)
                    <div class="bg-white border border-hairline rounded-xl">
                        <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-brand-ink capitalize">{{ $month }}</h3>
                            <span class="text-xs text-steel">{{ $documents->count() }} documento(s)</span>
                        </div>
                        <ul class="divide-y divide-hairline">
                            @foreach ($documents as $document)
                                <li class="px-4 py-2.5 flex items-center gap-3">
                                    <span class="text-xs text-steel w-20 shrink-0">{{ $document->expires_at->format('d/m/Y') }}</span>
                                    <span class="min-w-0 flex-1 text-sm text-brand-ink truncate">{{ $document->name }}</span>
                                    <span class="text-xs text-steel truncate max-w-[12rem]">{{ $document->company->display_name }}</span>
                                    <x-bid.status-badge :document="$document" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-app-layout>
