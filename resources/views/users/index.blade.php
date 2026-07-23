<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Usuários</h2>
    </x-slot>

    <x-page-header title="Usuários" subtitle="Gerencie o acesso da equipe ao sistema." icon="fa-users">
        <x-slot name="actions">
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo usuário
            </a>
        </x-slot>
    </x-page-header>

    {{-- Filtros --}}
    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome ou e-mail"
                          class="pl-9" />
        </div>
        <x-form.select name="role" class="sm:w-48" onchange="this.form.submit()">
            <option value="">Todos os perfis</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" class="sm:w-40" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </x-form.select>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($users->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-users" title="Nenhum usuário encontrado"
                           message="Ajuste os filtros ou cadastre um novo usuário." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Nome</th>
                <th class="px-4 py-3 font-medium">E-mail</th>
                <th class="px-4 py-3 font-medium">Perfil</th>
                <th class="px-4 py-3 font-medium">Setor</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($users as $u)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-user-avatar :user="$u" size="w-8 h-8 text-xs" />
                            <span class="font-medium text-brand-ink">{{ $u->name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $u->email }}</td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$u->isAdmin() ? 'dark' : ($u->isCoordenador() ? 'orange' : 'neutral')">
                            {{ $u->role->label() }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3 text-steel">{{ $u->setor?->nome ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$u->active ? 'success' : 'danger'">
                            {{ $u->active ? 'Ativo' : 'Inativo' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $u)
                                <a href="{{ route('users.edit', $u) }}"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            @endcan
                            @can('delete', $u)
                                <form method="POST" action="{{ route('users.destroy', $u) }}"
                                      data-confirm="Excluir o usuário {{ $u->name }}? Esta ação pode ser desfeita apenas pelo suporte.">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">
                {{ $users->links() }}
            </x-slot>
        </x-data-table>
    @endif
</x-app-layout>
