<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar empresa licitante</h2></x-slot>
    <x-page-header :title="$company->corporate_name" icon="fa-building-flag" subtitle="Edição do cadastro da empresa licitante." />
    @include('licitacoes.empresas._form')
</x-app-layout>
