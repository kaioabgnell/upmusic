<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Novo evento</h2></x-slot>
    <x-page-header title="Novo evento" icon="fa-calendar-days" />
    @include('eventos._form')
</x-app-layout>
