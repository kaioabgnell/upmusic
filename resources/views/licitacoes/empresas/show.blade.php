{{-- Cofre de documentos da empresa (specs/21 §9.3) + modal de cadastro/renovação (§9.4). --}}
@php
    $activeStatus = request('status');
    $tabs = [
        ['label' => 'Todos', 'value' => null, 'count' => $counters['total']],
        ['label' => 'Válidos', 'value' => 'valido', 'count' => $counters['valido']],
        ['label' => 'Vencendo', 'value' => 'vencendo', 'count' => $counters['vencendo']],
        ['label' => 'Vencidos', 'value' => 'vencido', 'count' => $counters['vencido']],
    ];

    // Payload dos tipos para o modal: o tipo canônico define a categoria e se exige código.
    $typePayload = $types->map(fn ($type) => [
        'id' => $type->id,
        'name' => $type->name,
        'category_id' => $type->bid_document_category_id,
        'requires_control_code' => $type->requires_control_code,
    ])->values();
@endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">{{ $company->display_name }}</h2></x-slot>

    <div x-data="{ tab: 'documentos' }">
        {{-- Cabeçalho da empresa --}}
        <div class="mb-6">
            <a href="{{ route('bid.companies.index') }}" class="inline-flex items-center gap-1.5 text-sm text-steel hover:text-brand-ink mb-3">
                <i class="fa-solid fa-arrow-left"></i> Empresas
            </a>

            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg text-white text-sm font-semibold shrink-0"
                          style="background-color: {{ $company->color ?: '#0a0a0a' }}">{{ $company->initials }}</span>
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl font-semibold text-brand-ink">{{ $company->corporate_name }}</h1>
                        <p class="text-sm text-steel">
                            {{ $company->cnpj_formatted }} &middot; {{ $company->size->shortLabel() }}
                            @unless ($company->active) &middot; <span class="text-red-600">inativa</span> @endunless
                        </p>
                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                            @foreach ($company->businessLines as $line)
                                <x-badge variant="neutral">{{ $line->name }}</x-badge>
                            @endforeach
                            @if ($company->primaryCnae())
                                <x-badge variant="neutral" icon="fa-industry">CNAE {{ $company->primaryCnae() }}</x-badge>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('bid.companies.edit', $company) }}"
                       class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                        <i class="fa-solid fa-pen"></i> Editar
                    </a>
                </div>
            </div>
        </div>

        {{-- Abas --}}
        <div class="border-b border-hairline mb-4 flex items-center gap-1">
            @foreach (['documentos' => 'Documentos', 'cadastro' => 'Dados cadastrais', 'historico' => 'Histórico'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-brand-orange text-brand-ink' : 'border-transparent text-steel hover:text-brand-ink'"
                        class="px-4 py-2 -mb-px border-b-2 text-sm font-medium transition-colors">
                    {{ $label }}
                    @if ($key === 'historico' && $superseded->isNotEmpty())
                        <span class="ml-1 text-xs text-steel">({{ $superseded->count() }})</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ABA DOCUMENTOS --}}
        <div x-show="tab === 'documentos'" x-data="bidDocument({
                storeUrl: '{{ route('bid.documents.store', $company) }}',
                readUrl: '{{ route('bid.documents.read') }}',
                companyId: {{ $company->id }},
                types: @js($typePayload),
             })">

            <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
                {{-- Abas de status (server-side, combinam com o filtro de categoria) --}}
                <div class="flex flex-wrap items-center gap-1">
                    @foreach ($tabs as $tab)
                        <a href="{{ route('bid.companies.show', array_filter([
                                'company' => $company->id,
                                'status' => $tab['value'],
                                'category' => request('category'),
                                'search' => request('search'),
                           ])) }}"
                           @class([
                               'inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                               'bg-brand-ink text-white' => $activeStatus === $tab['value'],
                               'border border-hairline text-brand-ink hover:bg-surface' => $activeStatus !== $tab['value'],
                           ])>
                            {{ $tab['label'] }}
                            <span @class(['text-xs', 'text-white/60' => $activeStatus === $tab['value'], 'text-steel' => $activeStatus !== $tab['value']])>
                                {{ $tab['count'] }}
                            </span>
                        </a>
                    @endforeach
                </div>

                <form method="GET" class="flex flex-1 items-center gap-2">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <div class="relative flex-1 min-w-[10rem]">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
                        <x-text-input name="search" :value="request('search')" placeholder="Buscar documento" class="pl-9" />
                    </div>
                    <x-form.select name="category" class="w-44" onchange="this.form.submit()">
                        <option value="">Todas as categorias</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </x-form.select>
                </form>

                <button type="button" @click="openCreate()"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors shrink-0">
                    <i class="fa-solid fa-plus"></i> Novo documento
                </button>
            </div>

            @if ($documents->isEmpty())
                <div class="bg-white border border-hairline rounded-xl">
                    @if (request()->hasAny(['status', 'category', 'search']))
                        <x-empty-state icon="fa-filter" title="Nenhum documento para este filtro"
                            message="Ajuste os filtros para ver outros documentos do acervo." />
                    @else
                        <x-empty-state icon="fa-folder-open" title="Acervo vazio"
                            message="Envie a primeira certidão. A IA lê o arquivo e sugere nome, categoria, validade e código de controle." />
                    @endif
                </div>
            @else
                <div class="bg-white border border-hairline rounded-xl divide-y divide-hairline">
                    @foreach ($documents as $document)
                        @php
                            $payload = [
                                'id' => $document->id,
                                'name' => $document->name,
                                'category_id' => $document->bid_document_category_id,
                                'type_id' => $document->bid_document_type_id,
                                'issuer' => $document->issuer,
                                'issued_at' => $document->issued_at?->toDateString(),
                                'expires_at' => $document->expires_at?->toDateString(),
                                'no_expiry' => $document->no_expiry,
                                'control_code' => $document->control_code,
                                'notes' => $document->notes,
                                'renewUrl' => route('bid.documents.renew', $document),
                                'updateUrl' => route('bid.documents.update', $document),
                            ];
                        @endphp
                        <div id="documento-{{ $document->id }}" class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 hover:bg-surface/60">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 min-w-0">
                                    <a href="{{ route('bid.documents.file', $document) }}" target="_blank" rel="noopener noreferrer"
                                       class="font-medium text-brand-ink truncate hover:underline">{{ $document->name }}</a>
                                    @if ($document->category)
                                        <x-badge variant="neutral">{{ $document->category->name }}</x-badge>
                                    @endif
                                    @if ($document->ai_extracted)
                                        <i class="fa-solid fa-wand-magic-sparkles text-xs text-steel"
                                           title="Cadastro preenchido com apoio da leitura por IA"></i>
                                    @endif
                                </div>
                                <p class="text-xs text-steel truncate mt-0.5">
                                    {{ $document->original_name }}
                                    @if ($document->control_code) &middot; código {{ $document->control_code }} @endif
                                    @if ($document->supersedes_id) &middot; renovação @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <div class="text-right">
                                    <x-bid.status-badge :document="$document" />
                                    <p class="text-xs text-steel mt-1">
                                        {{ $document->expires_at ? $document->expires_at->format('d/m/Y') : 'sem validade' }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-1">
                                    <a href="{{ route('bid.documents.file', ['document' => $document, 'download' => 1]) }}" title="Baixar"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <button type="button" @click="openRenew(@js($payload))" title="Renovar (nova versão)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                    <button type="button" @click="openEdit(@js($payload))" title="Editar dados"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <a href="{{ route('bid.documents.history', $document) }}" title="Histórico de versões"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                    <form method="POST" action="{{ route('bid.documents.destroy', $document) }}"
                                          data-confirm="Excluir {{ $document->name }}? A versão anterior não volta a ficar vigente.">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Excluir"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="px-4 py-3">{{ $documents->links() }}</div>
                </div>
            @endif

            @include('licitacoes.empresas._document-modal')
        </div>

        {{-- ABA DADOS CADASTRAIS --}}
        <div x-show="tab === 'cadastro'" x-cloak class="bg-white border border-hairline rounded-xl p-6">
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-sm">
                @php
                    $fields = [
                        'Razão social' => $company->corporate_name,
                        'Nome fantasia' => $company->trade_name ?: '—',
                        'CNPJ' => $company->cnpj_formatted,
                        'Porte' => $company->size->label(),
                        'Capital social' => $company->capital_social !== null ? \App\Support\Br::formatMoney((float) $company->capital_social) : '—',
                        'Patrimônio líquido' => $company->net_worth !== null ? \App\Support\Br::formatMoney((float) $company->net_worth) : '—',
                        'Regime tributário' => $company->tax_regime ?: '—',
                        'Responsável' => $company->responsible_name ?: '—',
                        'E-mail' => $company->email ?: '—',
                        'Telefone' => $company->phone ?: '—',
                        'Cidade/UF' => trim(($company->city ?: '—').($company->state ? ' / '.$company->state : '')),
                    ];
                @endphp
                @foreach ($fields as $label => $value)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-steel">{{ $label }}</dt>
                        <dd class="mt-0.5 text-brand-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($company->cnaes)
                <div class="mt-6 border-t border-hairline pt-5">
                    <p class="text-sm font-semibold text-brand-ink mb-2">CNAEs</p>
                    <ul class="space-y-1 text-sm">
                        @foreach ($company->cnaes as $cnae)
                            <li class="flex items-center gap-2">
                                <span class="font-mono text-brand-ink">{{ $cnae['code'] ?? '—' }}</span>
                                <span class="text-steel">{{ $cnae['description'] ?? '' }}</span>
                                @if ($cnae['primary'] ?? false)<x-badge variant="orange">Principal</x-badge>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($company->notes)
                <div class="mt-6 border-t border-hairline pt-5">
                    <p class="text-sm font-semibold text-brand-ink mb-1">Observações</p>
                    <p class="text-sm text-steel whitespace-pre-line">{{ $company->notes }}</p>
                </div>
            @endif
        </div>

        {{-- ABA HISTÓRICO --}}
        <div x-show="tab === 'historico'" x-cloak>
            @if ($superseded->isEmpty())
                <div class="bg-white border border-hairline rounded-xl">
                    <x-empty-state icon="fa-clock-rotate-left" title="Nenhuma versão anterior"
                        message="Quando um documento for renovado, a versão substituída aparece aqui." />
                </div>
            @else
                <x-data-table>
                    <x-slot name="head">
                        <th class="px-4 py-3 font-medium">Documento</th>
                        <th class="px-4 py-3 font-medium">Categoria</th>
                        <th class="px-4 py-3 font-medium">Validade</th>
                        <th class="px-4 py-3 font-medium">Substituído em</th>
                        <th class="px-4 py-3 font-medium">Enviado por</th>
                        <th class="px-4 py-3 font-medium text-right">Arquivo</th>
                    </x-slot>

                    @foreach ($superseded as $old)
                        @php
                            $lateDays = $old->expires_at && $old->superseded_at && $old->superseded_at->gt($old->expires_at)
                                ? $old->expires_at->diffInDays($old->superseded_at)
                                : null;
                        @endphp
                        <tr class="hover:bg-surface/60">
                            <td class="px-4 py-3 text-brand-ink">{{ $old->name }}</td>
                            <td class="px-4 py-3 text-steel">{{ $old->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-steel">{{ $old->expires_at?->format('d/m/Y') ?? 'sem validade' }}</td>
                            <td class="px-4 py-3 text-steel">
                                {{ $old->superseded_at?->format('d/m/Y') }}
                                @if ($lateDays)
                                    <span class="text-red-600 text-xs">({{ $lateDays }}d vencido)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-steel">{{ $old->uploader?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('bid.documents.file', $old) }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </div>
    </div>

    {{-- O modal de documento não reabre sozinho após um erro de validação (é preenchido só em
         memória pelo Alpine, que reseta no reload) — sem isto, uma falha de validação fecharia o
         modal em silêncio e pareceria "sumiu". Aqui pelo menos o motivo fica visível. --}}
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.upAlerts?.notifyError(@json($errors->first()), 'Documento não foi salvo');
            });
        </script>
    @endif
</x-app-layout>
