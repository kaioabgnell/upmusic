@php $isEdit = $item->exists; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">{{ $isEdit ? 'Editar item' : 'Novo item' }}</h2></x-slot>

    <x-page-header :title="$isEdit ? 'Editar item' : 'Novo item'" icon="fa-boxes-stacked" />

    <form method="POST" action="{{ $isEdit ? route('rentals.items.update', $item) : route('rentals.items.store') }}"
          x-data="{ rentalPrice: '{{ old('rental_price', $item->rental_price ? number_format($item->rental_price, 2, ',', '.') : '') }}',
                     unitCost: '{{ old('unit_cost', $item->unit_cost ? number_format($item->unit_cost, 2, ',', '.') : '') }}' }"
          class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <x-input-label for="name" value="Nome" />
                <x-text-input id="name" name="name" :value="old('name', $item->name)" class="mt-1" required placeholder="Ex.: Painel de LED 3x2, Mesa redonda, Telão 4x3" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="sku" value="Código (opcional)" />
                <x-text-input id="sku" name="sku" :value="old('sku', $item->sku)" class="mt-1" />
                <x-input-error :messages="$errors->get('sku')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="quantity" value="Quantidade" />
                <x-text-input id="quantity" type="number" min="1" name="quantity" :value="old('quantity', $item->quantity)" class="mt-1" required />
                <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="unit_cost" value="Valor do produto (unitário de compra)" />
                <input type="text" inputmode="decimal" id="unit_cost" name="unit_cost" x-model="unitCost"
                       @input="unitCost = maskMoneyDigits($event.target.value)" placeholder="0,00"
                       class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="purchase_date" value="Data de compra" />
                <x-text-input id="purchase_date" type="date" name="purchase_date" :value="old('purchase_date', optional($item->purchase_date)->format('Y-m-d'))" class="mt-1" />
                <x-input-error :messages="$errors->get('purchase_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="rental_price" value="Valor de locação (diária, referência)" />
                <input type="text" inputmode="decimal" id="rental_price" name="rental_price" x-model="rentalPrice"
                       @input="rentalPrice = maskMoneyDigits($event.target.value)" placeholder="0,00"
                       class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                <x-input-error :messages="$errors->get('rental_price')" class="mt-1" />
            </div>

            @if ($isEdit)
                <div class="flex items-center gap-2 pt-7">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $item->active))
                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                    <label for="active" class="text-sm text-brand-ink">Item ativo</label>
                </div>
            @endif

            <div class="sm:col-span-2">
                <x-input-label for="description" value="Descrição" />
                <textarea id="description" name="description" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('description', $item->description) }}</textarea>
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="notes" value="Observações" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('notes', $item->notes) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
            <a href="{{ route('rentals.items.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                <i class="fa-solid fa-floppy-disk"></i> Salvar
            </button>
        </div>
    </form>
</x-app-layout>
