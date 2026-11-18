<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Contas bancárias</h2></x-slot>

    <x-page-header title="Contas bancárias" subtitle="De onde sai cada pagamento e para onde entra cada recebimento do financeiro." icon="fa-building-columns">
        <x-slot name="actions">
            <a href="{{ route('bank-accounts.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Nova conta bancária
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row flex-wrap gap-3 mb-4">
        <div class="relative flex-1 min-w-[14rem]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome, banco, agência ou conta" class="pl-9" />
        </div>
        <x-form.select name="empresa_id" class="sm:w-52" onchange="this.form.submit()">
            <option value="">Todas as empresas</option>
            @foreach ($empresas as $empresa)
                <option value="{{ $empresa->id }}" @selected(request('empresa_id') == $empresa->id)>{{ $empresa->trade_name ?: $empresa->corporate_name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="account_type" class="sm:w-44" onchange="this.form.submit()">
            <option value="">Todos os tipos</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(request('account_type') === $value)>{{ $label }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" class="sm:w-36" onchange="this.form.submit()">
            <option value="">Todas</option>
            <option value="active" @selected(request('status') === 'active')>Ativas</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativas</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($bankAccounts->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            @if (request()->hasAny(['search', 'empresa_id', 'account_type', 'status']))
                <x-empty-state icon="fa-building-columns" title="Nenhuma conta bancária encontrada" message="Ajuste a busca ou limpe os filtros." />
            @else
                <x-empty-state icon="fa-building-columns" title="Nenhuma conta bancária cadastrada" message="Cadastre a primeira para identificar de onde saem e para onde entram os valores." />
            @endif
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Conta</th>
                <th class="px-4 py-3 font-medium">Banco</th>
                <th class="px-4 py-3 font-medium whitespace-nowrap">Agência / Conta</th>
                <th class="px-4 py-3 font-medium">Empresas</th>
                <th class="px-4 py-3 font-medium w-24">Em uso</th>
                <th class="px-4 py-3 font-medium w-24">Status</th>
                <th class="px-4 py-3 font-medium text-right w-24">Ações</th>
            </x-slot>

            @foreach ($bankAccounts as $bankAccount)
                @php
                    $usage = $bankAccount->payments_count + $bankAccount->revenues_count;
                    $empresaNames = $bankAccount->empresas->map(fn ($e) => $e->trade_name ?: $e->corporate_name);
                @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <p class="font-medium text-brand-ink">{{ $bankAccount->name }}</p>
                        <p class="text-xs text-steel mt-0.5">{{ $bankAccount->account_type->label() }}</p>
                    </td>
                    <td class="px-4 py-3 text-brand-ink">{{ $bankAccount->bank }}</td>
                    <td class="px-4 py-3 text-brand-ink whitespace-nowrap tabular-nums">
                        {{ $bankAccount->agency }} <span class="text-steel">/</span> {{ $bankAccount->account_number }}
                    </td>
                    <td class="px-4 py-3">
                        {{-- Até duas empresas pelo nome; as demais viram "+N" com a lista completa no tooltip. --}}
                        <div class="flex flex-wrap items-center gap-1" title="{{ $empresaNames->join(', ') }}">
                            @foreach ($empresaNames->take(2) as $empresaNome)
                                <x-badge variant="neutral">{{ Str::limit($empresaNome, 28) }}</x-badge>
                            @endforeach
                            @if ($empresaNames->count() > 2)
                                <span class="text-xs text-steel">+{{ $empresaNames->count() - 2 }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span title="{{ $bankAccount->payments_count }} pagamento(s), {{ $bankAccount->revenues_count }} receita(s)">
                            <x-badge variant="neutral">{{ $usage }}</x-badge>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$bankAccount->active ? 'success' : 'danger'">{{ $bankAccount->active ? 'Ativa' : 'Inativa' }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('bank-accounts.edit', $bankAccount) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            @if ($usage > 0)
                                {{-- Em uso não pode ser excluída (specs/26): o botão fica visível, mas explica o porquê. --}}
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel/40 cursor-not-allowed"
                                      title="Em uso em {{ $usage }} lançamento(s). Inative em vez de excluir.">
                                    <i class="fa-solid fa-trash"></i>
                                </span>
                            @else
                                <form method="POST" action="{{ route('bank-accounts.destroy', $bankAccount) }}"
                                      data-confirm="Excluir a conta bancária {{ $bankAccount->name }}?">
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

            <x-slot name="footer">{{ $bankAccounts->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
