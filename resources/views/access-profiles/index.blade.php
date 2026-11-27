<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Perfis de acesso</h2>
    </x-slot>

    <x-page-header title="Perfis de acesso" subtitle="Defina quais módulos e quadros cada perfil acessa." icon="fa-user-shield">
        <x-slot name="actions">
            <a href="{{ route('access-profiles.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-plus"></i> Novo perfil
            </a>
        </x-slot>
    </x-page-header>

    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-steel text-sm"></i>
            <x-text-input name="search" :value="request('search')" placeholder="Buscar por nome" class="pl-9" />
        </div>
        <button type="submit" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
    </form>

    @if ($profiles->isEmpty())
        <div class="bg-white border border-hairline rounded-xl">
            <x-empty-state icon="fa-user-shield" title="Nenhum perfil de acesso encontrado"
                           message="Ajuste a busca ou cadastre um novo perfil." />
        </div>
    @else
        <x-data-table>
            <x-slot name="head">
                <th class="px-4 py-3 font-medium">Nome</th>
                <th class="px-4 py-3 font-medium">Módulos</th>
                <th class="px-4 py-3 font-medium">Quadros</th>
                <th class="px-4 py-3 font-medium">Usuários</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
            </x-slot>

            @foreach ($profiles as $p)
                <tr class="hover:bg-surface/60">
                    <td class="px-4 py-3 font-medium text-brand-ink">{{ $p->name }}</td>
                    <td class="px-4 py-3 text-steel">{{ $p->modules_count }} de {{ $totalModules }}</td>
                    <td class="px-4 py-3">
                        @if ($p->configures_all_boards)
                            <x-badge variant="orange">Todos</x-badge>
                        @else
                            <span class="text-steel">{{ $p->boards_count }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('users.index', ['perfil' => $p->id]) }}" class="text-brand-orange-deep hover:underline">
                            {{ $p->users_count }} {{ $p->users_count === 1 ? 'usuário' : 'usuários' }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('access-profiles.edit', $p) }}"
                               class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('access-profiles.duplicate', $p) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink" title="Duplicar">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('access-profiles.destroy', $p) }}"
                                  data-confirm="Excluir o perfil de acesso {{ $p->name }}?">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach

            <x-slot name="footer">
                {{ $profiles->links() }}
            </x-slot>
        </x-data-table>
    @endif
</x-app-layout>
