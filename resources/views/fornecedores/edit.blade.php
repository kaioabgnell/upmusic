<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar fornecedor</h2></x-slot>
    <x-page-header title="Editar fornecedor" :subtitle="$fornecedor->name" icon="fa-truck-field" />
    @include('fornecedores._form')
</x-app-layout>
