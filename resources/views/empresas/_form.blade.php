@php
    $isEdit = isset($empresa);
    $type = old('type', $isEdit ? $empresa->type->value : 'PJ');
    $docValue = old('document', $isEdit
        ? ($empresa->type->value === 'PF' ? \App\Support\Br::formatCpf($empresa->document) : \App\Support\Br::formatCnpj($empresa->document))
        : '');
@endphp

<form method="POST" action="{{ $isEdit ? route('empresas.update', $empresa) : route('empresas.store') }}"
      x-data="cepLookup('{{ $type }}')"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-3xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="type" value="Tipo" />
            <x-form.select id="type" name="type" class="mt-1" x-model="type">
                <option value="PJ" @selected($type === 'PJ')>Pessoa Jurídica</option>
                <option value="PF" @selected($type === 'PF')>Pessoa Física</option>
            </x-form.select>
            <x-input-error :messages="$errors->get('type')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="corporate_name" x-text="type === 'PF' ? 'Nome' : 'Razão social'" />
            <x-text-input id="corporate_name" name="corporate_name" :value="old('corporate_name', $isEdit ? $empresa->corporate_name : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('corporate_name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="trade_name" value="Nome fantasia" />
            <x-text-input id="trade_name" name="trade_name" :value="old('trade_name', $isEdit ? $empresa->trade_name : '')" class="mt-1" />
            <x-input-error :messages="$errors->get('trade_name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="document" x-text="type === 'PF' ? 'CPF' : 'CNPJ'" />
            <x-text-input id="document" name="document" :value="$docValue" class="mt-1"
                          x-mask:dynamic="type === 'PF' ? '999.999.999-99' : '99.999.999/9999-99'"
                          x-bind:placeholder="type === 'PF' ? '000.000.000-00' : '00.000.000/0000-00'" required />
            <x-input-error :messages="$errors->get('document')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email', $isEdit ? $empresa->email : '')" class="mt-1" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="phone" value="Telefone" />
            <x-text-input id="phone" name="phone" :value="old('phone', $isEdit ? $empresa->phone : '')" class="mt-1" x-mask:dynamic="phoneMask" placeholder="(00) 00000-0000" />
            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
        </div>
    </div>

    <div class="border-t border-hairline pt-5">
        <p class="text-sm font-semibold text-brand-ink mb-3">Endereço</p>
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="zipcode" value="CEP" />
                <x-text-input id="zipcode" name="zipcode" x-model="zipcode" @input.debounce.400ms="fetchCep" @blur="fetchCep" :value="old('zipcode', $isEdit ? $empresa->zipcode : '')" class="mt-1" x-mask="99999-999" placeholder="00000-000" />
            </div>
            <div class="sm:col-span-4">
                <x-input-label for="address" value="Logradouro" />
                <x-text-input id="address" name="address" x-ref="address" :value="old('address', $isEdit ? $empresa->address : '')" class="mt-1" />
            </div>
            <div class="sm:col-span-1">
                <x-input-label for="number" value="Número" />
                <x-text-input id="number" name="number" :value="old('number', $isEdit ? $empresa->number : '')" class="mt-1" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="complement" value="Complemento" />
                <x-text-input id="complement" name="complement" :value="old('complement', $isEdit ? $empresa->complement : '')" class="mt-1" />
            </div>
            <div class="sm:col-span-3">
                <x-input-label for="district" value="Bairro" />
                <x-text-input id="district" name="district" x-ref="district" :value="old('district', $isEdit ? $empresa->district : '')" class="mt-1" />
            </div>
            <div class="sm:col-span-4">
                <x-input-label for="city" value="Cidade" />
                <x-text-input id="city" name="city" x-ref="city" :value="old('city', $isEdit ? $empresa->city : '')" class="mt-1" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="state" value="UF" />
                <x-text-input id="state" name="state" x-ref="state" :value="old('state', $isEdit ? $empresa->state : '')" class="mt-1" x-mask="aa" maxlength="2" />
            </div>
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Observações" />
        <textarea id="notes" name="notes" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('notes', $isEdit ? $empresa->notes : '') }}</textarea>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $isEdit ? $empresa->active : true)) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Empresa ativa</label>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('empresas.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>

@push('scripts')
<script>
    function cepLookup(initialType) {
        return {
            type: initialType,
            zipcode: @json(old('zipcode', $isEdit ? $empresa->zipcode : '')),
            lastCep: @json(old('zipcode', $isEdit ? $empresa->zipcode : ''))?.replace(/\D/g, '') || null,
            phoneMask(input) {
                return input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999';
            },
            async fetchCep() {
                const cep = (this.zipcode || '').replace(/\D/g, '');
                if (cep.length !== 8 || cep === this.lastCep) return;
                this.lastCep = cep;
                try {
                    const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                    const data = await res.json();
                    if (data.erro) {
                        window.upAlerts.notifyError('CEP não encontrado.');
                        return;
                    }
                    this.$refs.address.value = data.logradouro ?? '';
                    this.$refs.district.value = data.bairro ?? '';
                    this.$refs.city.value = data.localidade ?? '';
                    this.$refs.state.value = data.uf ?? '';
                } catch (e) {
                    window.upAlerts.notifyError('Não foi possível buscar o CEP. Preencha o endereço manualmente.');
                }
            },
        };
    }
</script>
@endpush
