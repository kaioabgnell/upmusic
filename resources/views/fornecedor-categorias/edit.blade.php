<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar categoria de fornecedor</h2></x-slot>
    <x-page-header title="Editar categoria de fornecedor" :subtitle="$categoria->nome" icon="fa-tags" />
    @include('fornecedor-categorias._form')
</x-app-layout>
