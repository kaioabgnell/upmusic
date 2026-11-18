<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Novo custo fixo</h2></x-slot>
    <x-page-header title="Novo custo fixo" subtitle="Cada parcela vira um card no quadro de custos fixos escolhido." icon="fa-arrows-rotate" />
    @include('custos-fixos._form')
</x-app-layout>
