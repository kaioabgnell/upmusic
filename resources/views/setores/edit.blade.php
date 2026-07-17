<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar setor</h2></x-slot>
    <x-page-header title="Editar setor" :subtitle="$setor->nome" icon="fa-sitemap" />
    @include('setores._form')
</x-app-layout>
