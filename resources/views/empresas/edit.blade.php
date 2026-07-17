<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar empresa</h2></x-slot>
    <x-page-header title="Editar empresa" :subtitle="$empresa->corporate_name" icon="fa-building" />
    @include('empresas._form')
</x-app-layout>
