<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar custo fixo</h2></x-slot>
    <x-page-header :title="'Editar: '.$fixedCost->name" subtitle="Mudanças valem para as parcelas ainda não geradas." icon="fa-arrows-rotate" />
    @include('custos-fixos._form')
</x-app-layout>
