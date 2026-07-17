<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Novo plano</h2></x-slot>
    <x-page-header title="Novo plano financeiro" icon="fa-chart-line" />

    <form method="POST" action="{{ route('plans.store') }}" class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
        @csrf
        @include('financeiro.planos._form-fields')
        <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
            <a href="{{ route('plans.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep"><i class="fa-solid fa-arrow-right"></i> Criar e lançar</button>
        </div>
    </form>
</x-app-layout>
