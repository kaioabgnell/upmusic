<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Novo template</h2></x-slot>
    <x-page-header title="Novo template" subtitle="Depois de criar, adicione os cards do template." icon="fa-clone" />

    <form method="POST" action="{{ route('templates.store') }}" class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <x-input-label for="name" value="Nome do template" />
                <x-text-input id="name" name="name" :value="old('name')" class="mt-1" required />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="board_id" value="Quadro alvo" />
                <x-form.select id="board_id" name="board_id" class="mt-1" required>
                    <option value="">— Selecione —</option>
                    @foreach ($boards as $b)
                        <option value="{{ $b->id }}" @selected(old('board_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </x-form.select>
                <x-input-error :messages="$errors->get('board_id')" class="mt-1" />
            </div>
        </div>
        <div>
            <x-input-label for="description" value="Descrição" />
            <textarea id="description" name="description" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('description') }}</textarea>
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="active" name="active" value="1" checked class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            <label for="active" class="text-sm text-brand-ink">Template ativo</label>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
            <a href="{{ route('templates.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-arrow-right"></i> Criar e adicionar cards
            </button>
        </div>
    </form>
</x-app-layout>
