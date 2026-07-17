<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Editar usuário</h2>
    </x-slot>

    <x-page-header title="Editar usuário" :subtitle="$user->name" icon="fa-user-pen" />

    @include('users._form')
</x-app-layout>
