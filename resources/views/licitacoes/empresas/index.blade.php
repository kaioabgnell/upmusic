{{-- Lista de empresas licitantes (specs/21 §9.2). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Licitações · Empresas</h2></x-slot>

    <x-page-header title="Empresas licitantes" icon="fa-building-flag"
        subtitle="Empresas do grupo que participam de licitações. Não confundir com o cadastro de clientes.">
        <x-slot name="actions">
            <a href="{{ route('bid.companies.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Nova empresa
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por razão social, fantasia ou CNPJ" class="pl-9" />
        </div>
        <x-form.select name="size" class="sm:w-44" onchange="this.form.submit()">
            <option value="">Todos os portes</option>
            @foreach (\App\Domain\Enums\BidCompanySize::cases() as $size)
                <option value="{{ $size->value }}" @selected(request('size') === $size->value)>{{ $size->shortLabel() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="business_line" class="sm:w-48" onchange="this.form.submit()">
            <option value="">Todos os ramos</option>
            @foreach ($lines as $line)
                <option value="{{ $line->id }}" @selected(request('business_line') == $line->id)>{{ $line->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" class="sm:w-36" onchange="this.form.submit()">
            <option value="">Todos</option>
            <option value="active" @selected(request('status') === 'active')>Ativas</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativas</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($companies->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-building-flag" title="Nenhuma empresa encontrada"
                message="Cadastre as empresas do grupo para montar o acervo de habilitação.">
                <x-slot name="action">
                    <a href="{{ route('bid.companies.create') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                        <i class="fa-solid fa-plus"></i> Nova empresa
                    </a>
                </x-slot>
            </x-empty-state>
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Empresa</th>
                <th class="px-4 py-3 font-medium">CNPJ</th>
                <th class="px-4 py-3 font-medium">Porte</th>
                <th class="px-4 py-3 font-medium">Ramos</th>
                <th class="px-4 py-3 font-medium">Saúde documental</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($companies as $company)
                @php $row = $health->get($company->id); @endphp
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <a href="{{ route('bid.companies.show', $company) }}" class="flex items-center gap-3 group">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white text-xs font-semibold shrink-0"
                                  style="background-color: {{ $company->color ?: '#0a0a0a' }}">{{ $company->initials }}</span>
                            <span class="min-w-0">
                                <span class="block font-medium text-brand-ink truncate group-hover:underline">{{ $company->corporate_name }}</span>
                                @if ($company->trade_name)
                                    <span class="block text-xs text-steel truncate">{{ $company->trade_name }}</span>
                                @endif
                            </span>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-steel whitespace-nowrap">{{ $company->cnpj_formatted }}</td>
                    <td class="px-4 py-3"><x-badge variant="neutral">{{ $company->size->shortLabel() }}</x-badge></td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @forelse ($company->businessLines as $line)
                                <x-badge variant="neutral">{{ $line->name }}</x-badge>
                            @empty
                                <span class="text-steel text-xs">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @if ($row)
                            <x-bid.health-bar :ok="$row['ok']" :total="$row['total']" :percent="$row['percent']" />
                        @else
                            <span class="text-steel text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$company->active ? 'success' : 'danger'">{{ $company->active ? 'Ativa' : 'Inativa' }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('bid.companies.show', $company) }}" title="Documentos"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                <i class="fa-solid fa-folder-open"></i>
                            </a>
                            <a href="{{ route('bid.companies.edit', $company) }}" title="Editar"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('bid.companies.destroy', $company) }}"
                                  data-confirm="Excluir a empresa {{ $company->corporate_name }} e todo o seu acervo de documentos?">
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

            <x-slot name="footer">{{ $companies->links() }}</x-slot>
        </x-data-table>
    @endif
</x-app-layout>
