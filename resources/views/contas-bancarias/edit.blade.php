<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar conta bancária</h2></x-slot>
    <x-page-header title="Editar conta bancária" :subtitle="$bankAccount->label" icon="fa-building-columns" />
    @include('contas-bancarias._form')
</x-app-layout>
