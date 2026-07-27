<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Nova empresa licitante</h2></x-slot>
    <x-page-header title="Nova empresa licitante" icon="fa-building-flag"
        subtitle="Cadastre os dados que o edital costuma exigir: porte, CNAEs, capital e patrimônio." />
    @include('licitacoes.empresas._form')
</x-app-layout>
