{{-- Campos do tipo canônico de documento (specs/21 §6.4). Compartilhado por criação e edição. --}}
<div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
    <div class="sm:col-span-3">
        <x-input-label value="Nome oficial" />
        <x-text-input name="name" class="mt-1" required :value="$type?->name" />
    </div>
    <div class="sm:col-span-2">
        <x-input-label value="Categoria" />
        <x-form.select name="bid_document_category_id" class="mt-1" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($type?->bid_document_category_id === $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-form.select>
    </div>
    <div>
        <x-input-label value="Identificador" />
        <x-text-input name="slug" class="mt-1" :value="$type?->slug" placeholder="cnd_federal" />
    </div>

    <div class="sm:col-span-6">
        <x-input-label value="Apelidos (variações que aparecem nos editais)" />
        <textarea name="aliases" rows="2"
                  class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm"
                  placeholder="cnd federal, certidao conjunta pgfn, certidao negativa federal">{{ $type?->aliases ? implode(', ', $type->aliases) : '' }}</textarea>
        <p class="mt-1 text-xs text-steel">
            Separe por vírgula. São normalizados (sem acento, minúsculas) e comparados com o nome do
            requisito extraído do edital.
        </p>
    </div>

    <div class="sm:col-span-2">
        <x-input-label value="Órgão emissor" />
        <x-text-input name="issuer" class="mt-1" :value="$type?->issuer" />
    </div>
    <div>
        <x-input-label value="Validade padrão (dias)" />
        <x-text-input type="number" name="default_validity_days" class="mt-1" min="1" max="3650" :value="$type?->default_validity_days" />
    </div>
    <div>
        <x-input-label value="Ordem" />
        <x-text-input type="number" name="sort_order" class="mt-1" min="0" :value="$type?->sort_order ?? 0" />
    </div>

    <div class="sm:col-span-2 flex flex-col justify-center gap-2">
        <label class="flex items-center gap-2 text-sm text-brand-ink">
            <input type="hidden" name="requires_control_code" value="0">
            <input type="checkbox" name="requires_control_code" value="1" @checked($type?->requires_control_code)
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            Exige código de controle
        </label>
        <label class="flex items-center gap-2 text-sm text-brand-ink">
            <input type="hidden" name="essential" value="0">
            <input type="checkbox" name="essential" value="1" @checked($type?->essential)
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            Essencial (entra na saúde documental)
        </label>
        <label class="flex items-center gap-2 text-sm text-brand-ink">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked($type?->active ?? true)
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            Ativo
        </label>
    </div>
</div>
