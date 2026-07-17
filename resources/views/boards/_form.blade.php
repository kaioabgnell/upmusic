@php $isEdit = isset($board); @endphp

<form method="POST" action="{{ $isEdit ? route('boards.update', $board) : route('boards.store') }}"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="name" value="Nome do quadro" />
            <x-text-input id="name" name="name" :value="old('name', $isEdit ? $board->name : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="setor_id" value="Setor / Departamento" />
            <x-form.select id="setor_id" name="setor_id" class="mt-1">
                <option value="">— Sem setor —</option>
                @foreach ($setores as $setor)
                    <option value="{{ $setor->id }}" @selected((string) old('setor_id', $isEdit ? $board->setor_id : '') === (string) $setor->id)>{{ $setor->nome }}</option>
                @endforeach
            </x-form.select>
            <x-input-error :messages="$errors->get('setor_id')" class="mt-1" />
        </div>
    </div>

    <div>
        <x-input-label for="description" value="Descrição" />
        <textarea id="description" name="description" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('description', $isEdit ? $board->description : '') }}</textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="color" value="Cor" />
            <input type="color" id="color" name="color" value="{{ old('color', $isEdit ? $board->color : '#ff8c1e') }}" class="mt-1 h-10 w-20 rounded-md border border-hairline cursor-pointer">
        </div>
        <div>
            <x-input-label for="icon" value="Ícone (Font Awesome)" />
            <x-text-input id="icon" name="icon" :value="old('icon', $isEdit ? $board->icon : '')" class="mt-1" placeholder="fa-table-columns" />
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $isEdit ? $board->active : true)) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Quadro ativo</label>
    </div>

    @unless ($isEdit)
        <div class="flex items-center gap-2">
            <input type="hidden" name="with_default_columns" value="0">
            <input type="checkbox" id="with_default_columns" name="with_default_columns" value="1" checked class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            <label for="with_default_columns" class="text-sm text-brand-ink">Iniciar com colunas padrão (A Fazer, Em Andamento, Concluído)</label>
        </div>
    @endunless

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('boards.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
