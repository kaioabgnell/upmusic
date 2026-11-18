@php $isEdit = isset($costCenter); @endphp

<form method="POST" action="{{ $isEdit ? route('cost-centers.update', $costCenter) : route('cost-centers.store') }}"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-5">
        <div>
            <x-input-label for="code" value="Código" />
            <x-text-input id="code" name="code" :value="old('code', $isEdit ? $costCenter->code : '')" class="mt-1" maxlength="30" placeholder="MKT-01" />
            <x-input-error :messages="$errors->get('code')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" :value="old('name', $isEdit ? $costCenter->name : '')" class="mt-1" maxlength="120" required />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
    </div>
    <p class="-mt-3 text-xs text-steel">O código é opcional. Quando existe, aparece antes do nome nas listas e define a ordem delas.</p>

    <div>
        <x-input-label for="description" value="Descrição" />
        <textarea id="description" name="description" rows="3" maxlength="1000"
                  class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('description', $isEdit ? $costCenter->description : '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    <div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="active" name="active" value="1"
                   @checked(old('active', $isEdit ? $costCenter->active : true))
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            <label for="active" class="text-sm text-brand-ink">Centro de custo ativo</label>
        </div>
        <p class="mt-1 text-xs text-steel">Inativo não aparece para novas escolhas, mas continua nas linhas que já o usam.</p>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('cost-centers.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
