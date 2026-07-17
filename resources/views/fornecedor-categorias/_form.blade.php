@php $isEdit = isset($categoria); @endphp

<form method="POST" action="{{ $isEdit ? route('fornecedor-categorias.update', $categoria) : route('fornecedor-categorias.store') }}"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <x-input-label for="nome" value="Nome" />
        <x-text-input id="nome" name="nome" :value="old('nome', $isEdit ? $categoria->nome : '')" class="mt-1" required autofocus />
        <x-input-error :messages="$errors->get('nome')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="unidade" value="Unidade" />
        @php $unidadeAtual = old('unidade', $isEdit ? $categoria->unidade?->value : ''); @endphp
        <x-form.select id="unidade" name="unidade" class="mt-1">
            <option value="">— Selecione —</option>
            @foreach (\App\Domain\Enums\UnidadeMedida::options() as $value => $label)
                <option value="{{ $value }}" @selected($unidadeAtual === $value)>{{ $label }}</option>
            @endforeach
        </x-form.select>
        <x-input-error :messages="$errors->get('unidade')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="preco_interno" value="Preço Interno" />
        @php
            $precoInternoAtual = old('preco_interno', $isEdit && $categoria->preco_interno !== null ? number_format((float) $categoria->preco_interno, 2, ',', '.') : '');
        @endphp
        <div class="relative mt-1">
            <span class="absolute inset-y-0 left-3 flex items-center text-sm text-steel">R$</span>
            <input type="text" inputmode="decimal" id="preco_interno" name="preco_interno" value="{{ $precoInternoAtual }}"
                   x-data x-mask:dynamic="$money($input, ',')" placeholder="0,00"
                   class="pl-9 block w-full rounded-md border-gray-300 focus:border-brand-orange focus:ring-brand-orange text-sm">
        </div>
        <x-input-error :messages="$errors->get('preco_interno')" class="mt-1" />
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1"
               @checked(old('active', $isEdit ? $categoria->active : true))
               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Categoria ativa</label>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('fornecedor-categorias.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
