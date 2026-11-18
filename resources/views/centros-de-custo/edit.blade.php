<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Editar centro de custo</h2></x-slot>
    <x-page-header title="Editar centro de custo" :subtitle="$costCenter->label" icon="fa-diagram-project" />
    @include('centros-de-custo._form')
</x-app-layout>
