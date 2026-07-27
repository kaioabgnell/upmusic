{{-- Resultado da análise (specs/21 §9.6): ranking de aptidão + matriz de conformidade.
     Cada célula abre o painel com o documento vinculado e o TRECHO DO EDITAL que originou a
     exigência — sem rastreabilidade, um sistema de habilitação é palpite caro. --}}
@php
    use App\Domain\Enums\BidMatchStatus;

    $companies = $notice->evaluations->pluck('company')->filter();

    // Payloads para o painel lateral: dados prontos, para o clique não fazer requisição.
    $requirementPayload = $notice->requirements->mapWithKeys(fn ($r) => [$r->id => [
        'id' => $r->id,
        'name' => $r->name,
        'description' => $r->description,
        'kind' => $r->kind->label(),
        'kindValue' => $r->kind->value,
        'mandatory' => $r->mandatory,
        'ignored' => $r->ignored,
        'ignoredReason' => $r->ignored_reason,
        'typeName' => $r->type?->name,
        'typeId' => $r->bid_document_type_id,
        'categoryName' => $r->category?->name,
        'excerpt' => $r->source_excerpt,
        'page' => $r->source_page,
        'expected' => $r->expected,
        'updateUrl' => route('bid.requirements.update', $r),
    ]]);

    $companyPayload = $companies->mapWithKeys(fn ($c) => [$c->id => [
        'id' => $c->id,
        'name' => $c->display_name,
        'color' => $c->color,
    ]]);

    $matrixPayload = $matrix->map(fn ($rows) => $rows->map(fn ($m) => [
        'id' => $m->id,
        'status' => $m->status->value,
        'statusLabel' => $m->status->label(),
        'icon' => $m->status->icon(),
        'classes' => $m->status->classes(),
        'reason' => $m->reason,
        'confidence' => $m->confidence,
        'manual' => $m->manual_override,
        'documentId' => $m->bid_document_id,
        'documentName' => $m->document?->name,
        'documentUrl' => $m->bid_document_id ? route('bid.documents.file', $m->bid_document_id) : null,
        'updateUrl' => route('bid.matches.update', $m),
        'resetUrl' => route('bid.matches.reset', $m),
    ]));

    $documentPayload = $documentsByCompany->map(fn ($docs) => $docs->map(fn ($d) => [
        'id' => $d->id,
        'name' => $d->name.($d->no_expiry ? ' (sem validade)' : ' — vence '.$d->expires_at?->format('d/m/Y')),
    ])->values());
@endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink truncate">{{ $notice->title }}</h2></x-slot>

    <div x-data="bidNotice()">
        <a href="{{ route('bid.notices.index') }}" class="inline-flex items-center gap-1.5 text-sm text-steel hover:text-brand-ink mb-3">
            <i class="fa-solid fa-arrow-left"></i> Editais
        </a>

        {{-- Cabeçalho do edital --}}
        <div class="bg-white border border-hairline rounded-xl p-5 mb-4">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-brand-ink">{{ $notice->title }}</h1>
                    <p class="text-sm text-steel mt-1">
                        {{ $notice->agency ?: 'Órgão não identificado' }}
                        @if ($notice->number) &middot; nº {{ $notice->number }} @endif
                        @if ($notice->modality) &middot; {{ $notice->modality }} @endif
                        @if ($notice->city) &middot; {{ $notice->city }}@if($notice->uf)/{{ $notice->uf }}@endif @endif
                    </p>

                    @if ($notice->object_summary)
                        <p class="text-sm text-brand-ink mt-3"><span class="text-steel">Objeto:</span> {{ $notice->object_summary }}</p>
                    @endif

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-xs text-steel">
                        @if ($notice->session_at)
                            <span><i class="fa-solid fa-calendar-day"></i> Sessão {{ $notice->session_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if ($notice->estimated_value !== null)
                            <span><i class="fa-solid fa-sack-dollar"></i> {{ \App\Support\Br::formatMoney((float) $notice->estimated_value) }}</span>
                        @endif
                        @if ($notice->me_epp_exclusive !== null)
                            <span><i class="fa-solid fa-building-flag"></i> ME/EPP: {{ $notice->me_epp_exclusive ? 'exclusivo' : 'não exclusivo' }}</span>
                        @endif
                        @if ($notice->requires_site_visit)
                            <span><i class="fa-solid fa-person-walking"></i> exige visita técnica</span>
                        @endif
                        @if ($notice->requires_bid_bond)
                            <span><i class="fa-solid fa-shield-halved"></i> exige garantia de proposta</span>
                        @endif
                        <span><i class="fa-solid fa-list-check"></i> {{ $notice->requirements->count() }} requisitos</span>
                        <span><i class="fa-solid fa-wand-magic-sparkles"></i> confiança da leitura: {{ $notice->confidence_label }}</span>
                        @if ($notice->file_path)
                            <a href="{{ route('bid.notices.matrix', $notice) }}" class="text-brand-orange-deep hover:underline">
                                <i class="fa-solid fa-file-csv"></i> exportar matriz
                            </a>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="reprocess('{{ route('bid.notices.reprocess', $notice) }}')"
                            x-bind:disabled="submitting"
                            class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface disabled:opacity-50">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Reprocessar
                    </button>

                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-hairline text-steel hover:bg-surface hover:text-brand-ink">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <button type="button" @click="$dispatch('open-notice-edit')"
                                    class="block w-full px-4 py-2 text-left text-sm text-brand-ink hover:bg-surface">
                                <i class="fa-solid fa-pen w-5 text-center text-steel"></i> Corrigir dados do edital
                            </button>
                            <a href="{{ route('bid.notices.matrix', $notice) }}"
                               class="block w-full px-4 py-2 text-left text-sm text-brand-ink hover:bg-surface">
                                <i class="fa-solid fa-file-csv w-5 text-center text-steel"></i> Exportar matriz (CSV)
                            </a>
                            <form method="POST" action="{{ route('bid.notices.reevaluate', $notice) }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-brand-ink hover:bg-surface">
                                    <i class="fa-solid fa-calculator w-5 text-center text-steel"></i> Recalcular aptidão
                                </button>
                            </form>
                            <form method="POST" action="{{ route('bid.notices.destroy', $notice) }}"
                                  data-confirm="Excluir esta análise? Requisitos e conferências serão perdidos.">
                                @csrf @method('DELETE')
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-trash w-5 text-center"></i> Excluir análise
                                </button>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            @if ($notice->ai_warnings)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-800 mb-1"><i class="fa-solid fa-triangle-exclamation"></i> Avisos da leitura</p>
                    <ul class="list-disc list-inside text-xs text-amber-800 space-y-0.5">
                        @foreach ($notice->ai_warnings as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Estados que exigem ação --}}
        @if ($notice->status === \App\Domain\Enums\BidNoticeStatus::Erro)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 mb-4 flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation text-red-600 mt-0.5"></i>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-red-800">A leitura do edital falhou</p>
                    <p class="text-sm text-red-700">{{ $notice->error_message ?: 'Erro não detalhado.' }}</p>
                    <button type="button" @click="reprocess('{{ route('bid.notices.reprocess', $notice) }}')"
                            class="mt-2 inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                        <i class="fa-solid fa-rotate"></i> Reprocessar
                    </button>
                </div>
            </div>
        @elseif ($notice->is_stale)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 mb-4 flex items-start gap-3">
                <i class="fa-solid fa-plug-circle-xmark text-red-600 mt-0.5"></i>
                <div>
                    <p class="text-sm font-semibold text-red-800">Análise interrompida</p>
                    <p class="text-sm text-red-700">
                        A leitura começou mas não terminou (aba fechada, queda de rede ou tempo esgotado no
                        servidor). Nada foi perdido — reprocesse para concluir.
                    </p>
                    <button type="button" @click="reprocess('{{ route('bid.notices.reprocess', $notice) }}')"
                            class="mt-2 inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                        <i class="fa-solid fa-rotate"></i> Reprocessar
                    </button>
                </div>
            </div>
        @elseif ($notice->status === \App\Domain\Enums\BidNoticeStatus::Processando)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 mb-4 text-sm text-amber-800">
                <i class="fa-solid fa-circle-notch fa-spin"></i> A análise está em andamento nesta ou em outra aba.
            </div>
        @endif

        @if ($recalculated)
            <div class="rounded-xl border border-hairline bg-white px-4 py-3 mb-4 text-sm text-steel">
                <i class="fa-solid fa-calculator text-brand-orange-deep"></i>
                A aptidão foi <strong class="text-brand-ink">recalculada agora</strong> porque o acervo mudou desde a última avaliação.
            </div>
        @endif

        {{-- RANKING DE APTIDÃO --}}
        @if ($notice->evaluations->isNotEmpty())
            <h2 class="text-sm font-semibold uppercase tracking-wide text-steel mb-3">Ranking de aptidão</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                @foreach ($notice->evaluations as $evaluation)
                    @php $lowConf = $lowConfidence[$evaluation->bid_company_id] ?? 0; @endphp
                    <div @class([
                        'bg-white border rounded-xl p-4 flex flex-col',
                        'border-green-300' => $evaluation->verdict === \App\Domain\Enums\BidVerdict::Apta,
                        'border-amber-300' => $evaluation->verdict === \App\Domain\Enums\BidVerdict::AptaComPendencias,
                        'border-hairline' => $evaluation->verdict === \App\Domain\Enums\BidVerdict::Inapta,
                    ])>
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs text-steel">{{ $evaluation->rank }}º lugar</p>
                                <a href="{{ route('bid.companies.show', $evaluation->bid_company_id) }}"
                                   class="block font-semibold text-brand-ink truncate hover:underline">
                                    {{ $evaluation->company?->display_name }}
                                </a>
                            </div>
                            <span class="text-2xl font-semibold text-brand-ink shrink-0">{{ number_format((float) $evaluation->score, 0) }}</span>
                        </div>

                        <div class="mt-2">
                            <x-badge :variant="$evaluation->verdict->badgeVariant()" :icon="$evaluation->verdict->icon()">
                                {{ $evaluation->verdict->label() }}
                            </x-badge>
                        </div>

                        <p class="mt-3 text-xs text-steel">
                            {{ $evaluation->met_count }} atendidos ·
                            {{ $evaluation->expiring_count }} vencendo ·
                            {{ $evaluation->missing_count }} faltando ·
                            {{ $evaluation->review_count }} a conferir
                        </p>

                        @if ($evaluation->blockers)
                            <ul class="mt-3 space-y-1">
                                @foreach (array_slice($evaluation->blockers, 0, 3) as $blocker)
                                    <li class="text-xs text-red-700 flex gap-1.5">
                                        <i class="fa-solid fa-circle-xmark mt-0.5 shrink-0"></i>
                                        <span>{{ $blocker }}</span>
                                    </li>
                                @endforeach
                                @if (count($evaluation->blockers) > 3)
                                    <li class="text-xs text-steel">+ {{ count($evaluation->blockers) - 3 }} outro(s) bloqueio(s)</li>
                                @endif
                            </ul>
                        @endif

                        @if ($evaluation->highlights)
                            <ul class="mt-3 space-y-1">
                                @foreach (array_slice($evaluation->highlights, 0, 3) as $highlight)
                                    <li class="text-xs text-green-700 flex gap-1.5">
                                        <i class="fa-solid fa-circle-check mt-0.5 shrink-0"></i>
                                        <span>{{ $highlight }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($lowConf)
                            <p class="mt-3 text-xs text-amber-700">
                                <i class="fa-solid fa-circle-question"></i>
                                {{ $lowConf }} vínculo(s) por semelhança de nome — confirmar na matriz.
                            </p>
                        @endif

                        <a href="{{ route('bid.notices.plan', ['notice' => $notice, 'company' => $evaluation->bid_company_id]) }}"
                           class="mt-4 inline-flex items-center justify-center gap-2 rounded-md border border-hairline px-3 py-2 text-xs font-semibold text-brand-ink hover:bg-surface">
                            <i class="fa-solid fa-clipboard-list"></i> Ver checklist / plano
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- MATRIZ DE CONFORMIDADE --}}
        @if ($notice->requirements->isEmpty())
            <div class="bg-white border border-hairline rounded-xl">
                <x-empty-state icon="fa-list-check" title="Nenhum requisito extraído"
                    message="A leitura não identificou requisitos de habilitação. Reprocesse o edital ou cole o texto da seção de habilitação." />
            </div>
        @else
            <div x-data="bidMatrix({
                    requirements: @js($requirementPayload),
                    companies: @js($companyPayload),
                    matrix: @js($matrixPayload),
                    documents: @js($documentPayload),
                 })">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-steel">Matriz de conformidade</h2>
                    <div class="flex items-center gap-1">
                        @foreach (['all' => 'Todos', 'mandatory' => 'Só obrigatórios', 'issues' => 'Só pendências'] as $key => $label)
                            <button type="button" @click="filter = '{{ $key }}'"
                                    :class="filter === '{{ $key }}' ? 'bg-brand-ink text-white' : 'border border-hairline text-brand-ink hover:bg-surface'"
                                    class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- A tabela rola dentro do próprio container: a página nunca rola na horizontal. --}}
                <div class="bg-white border border-hairline rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface">
                                <tr class="text-left text-steel">
                                    <th class="sticky left-0 z-10 bg-surface px-4 py-3 font-medium min-w-[18rem]">Requisito</th>
                                    @foreach ($companies as $company)
                                        <th class="px-3 py-3 font-medium text-center whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="inline-block w-2 h-2 rounded-full" style="background-color: {{ $company->color ?: '#0a0a0a' }}"></span>
                                                {{ $company->trade_name ?: \Illuminate\Support\Str::limit($company->corporate_name, 18) }}
                                            </span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                @foreach ($notice->requirements as $requirement)
                                    @php
                                        $rowMatches = $matrix->get($requirement->id) ?? collect();
                                        $hasIssue = $rowMatches->contains(fn ($m) => in_array($m->status, [
                                            BidMatchStatus::Ausente, BidMatchStatus::Vencido, BidMatchStatus::Vencendo, BidMatchStatus::Conferir,
                                        ], true));
                                    @endphp
                                    <tr class="hover:bg-surface/60"
                                        x-show="rowVisible({ mandatory: {{ $requirement->mandatory ? 'true' : 'false' }}, hasIssue: {{ $hasIssue ? 'true' : 'false' }} })">
                                        <td class="sticky left-0 z-10 bg-white px-4 py-3">
                                            <button type="button" @click="openCell({{ $requirement->id }}, {{ $companies->first()?->id ?? 'null' }})"
                                                    class="text-left group">
                                                <span class="block font-medium text-brand-ink group-hover:underline">
                                                    {{ $requirement->name }}
                                                    @if ($requirement->ignored)
                                                        <span class="text-xs text-steel">(não aplicável)</span>
                                                    @endif
                                                </span>
                                                <span class="block text-xs text-steel">
                                                    {{ $requirement->kind->label() }}
                                                    &middot; {{ $requirement->mandatory ? 'obrigatório' : 'opcional' }}
                                                    @if ($requirement->source_page) &middot; p. {{ $requirement->source_page }} @endif
                                                </span>
                                            </button>
                                        </td>

                                        @foreach ($companies as $company)
                                            @php $match = $rowMatches->get($company->id); @endphp
                                            <td class="px-3 py-3 text-center">
                                                @if ($match)
                                                    <button type="button" @click="openCell({{ $requirement->id }}, {{ $company->id }})"
                                                            class="inline-flex flex-col items-center gap-0.5 group"
                                                            title="{{ $match->reason }}">
                                                        <i class="fa-solid {{ $match->status->icon() }} {{ $match->status->classes() }} group-hover:scale-110 transition-transform"></i>
                                                        @if ($match->status === BidMatchStatus::Vencendo && $match->document)
                                                            <span class="text-[10px] text-amber-600">{{ max(0, $match->document->days_to_expire) }}d</span>
                                                        @endif
                                                        @if ($match->manual_override)
                                                            <span class="text-[10px] text-steel" title="Definido manualmente">manual</span>
                                                        @elseif ($match->confidence === 'baixa' && in_array($match->status, [BidMatchStatus::Atendido, BidMatchStatus::Vencendo], true))
                                                            <span class="text-[10px] text-amber-600" title="Vínculo por semelhança de nome — confirmar">?</span>
                                                        @endif
                                                    </button>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 border-t border-hairline text-xs text-steel">
                        <span><i class="fa-solid fa-circle-check text-green-600"></i> atendido</span>
                        <span><i class="fa-solid fa-clock text-amber-600"></i> vencendo</span>
                        <span><i class="fa-solid fa-circle-xmark text-red-600"></i> vencido/ausente</span>
                        <span><i class="fa-solid fa-circle-question text-steel"></i> exige conferência humana</span>
                        <span><i class="fa-solid fa-minus text-gray-300"></i> não aplicável</span>
                        <span class="text-steel/70">Clique em qualquer célula para ver o trecho do edital e vincular documentos.</span>
                    </div>
                </div>

                @include('licitacoes.editais._requirement-panel')
            </div>
        @endif

        @include('licitacoes.editais._notice-edit-modal')
    </div>
</x-app-layout>
