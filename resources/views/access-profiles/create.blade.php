<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Novo perfil de acesso</h2>
    </x-slot>

    <x-page-header title="Novo perfil de acesso" subtitle="Escolha os módulos e quadros que este perfil acessa." icon="fa-user-shield" />

    @include('access-profiles._form')
</x-app-layout>
