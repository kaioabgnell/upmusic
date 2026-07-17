@php $isEdit = isset($setor); @endphp

<form method="POST" action="{{ $isEdit ? route('setores.update', $setor) : route('setores.store') }}"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <x-input-label for="nome" value="Nome" />
        <x-text-input id="nome" name="nome" :value="old('nome', $isEdit ? $setor->nome : '')" class="mt-1" required />
        <x-input-error :messages="$errors->get('nome')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="descricao" value="Descrição" />
        <textarea id="descricao" name="descricao" rows="3"
                  class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('descricao', $isEdit ? $setor->descricao : '') }}</textarea>
        <x-input-error :messages="$errors->get('descricao')" class="mt-1" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="color" value="Cor" />
            <input type="color" id="color" name="color" value="{{ old('color', $isEdit ? $setor->color : '#000000') }}"
                   class="mt-1 h-10 w-20 rounded-md border border-hairline cursor-pointer">
            <x-input-error :messages="$errors->get('color')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="icon" value="Ícone (classe Font Awesome)" />
            <x-text-input id="icon" name="icon" :value="old('icon', $isEdit ? $setor->icon : '')" class="mt-1" placeholder="fa-sitemap" />
            <p class="text-xs text-steel mt-1">Ex.: fa-file-invoice-dollar, fa-money-bill-wave</p>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1"
               @checked(old('active', $isEdit ? $setor->active : true))
               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Setor ativo</label>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('setores.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
