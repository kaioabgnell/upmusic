<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Editar perfil de acesso</h2>
    </x-slot>

    <x-page-header title="Editar perfil de acesso" :subtitle="$profile->name" icon="fa-user-shield" />

    @include('access-profiles._form')
</x-app-layout>
