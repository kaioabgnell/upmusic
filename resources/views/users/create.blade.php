<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Novo usuário</h2>
    </x-slot>

    <x-page-header title="Novo usuário" subtitle="Cadastre um novo acesso ao sistema." icon="fa-user-plus" />

    @include('users._form')
</x-app-layout>
