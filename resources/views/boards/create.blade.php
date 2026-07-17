<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Novo quadro</h2></x-slot>
    <x-page-header title="Novo quadro" subtitle="Crie um departamento e seu fluxo." icon="fa-table-columns" />
    @include('boards._form')
</x-app-layout>
