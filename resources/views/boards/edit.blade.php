<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar quadro</h2></x-slot>
    <x-page-header title="Editar quadro" :subtitle="$board->name" icon="fa-table-columns">
        <x-slot name="actions">
            <a href="{{ route('boards.config', $board) }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-gear"></i> Configurar colunas e campos
            </a>
        </x-slot>
    </x-page-header>
    @include('boards._form')
</x-app-layout>
