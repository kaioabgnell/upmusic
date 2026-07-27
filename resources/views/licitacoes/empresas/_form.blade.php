{{-- Cadastro/edição de empresa licitante (specs/21 §9.2): identificação, perfil e contato. --}}
@php
    $isEdit = $company->exists;
    $cnaes = old('cnaes', ($company->cnaes ?: [['code' => '', 'description' => '', 'primary' => true]]));
    $selectedLines = old('business_lines', $isEdit ? $company->businessLines->pluck('id')->all() : []);
@endphp

<form method="POST" action="{{ $isEdit ? route('bid.companies.update', $company) : route('bid.companies.store') }}"
      x-data="bidCompanyForm(@js($cnaes))"
      class="bg-white border border-hairline rounded-xl p-6 space-y-6 max-w-4xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    {{-- Identificação --}}
    <div>
        <p class="text-sm font-semibold text-brand-ink mb-3">Identificação</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <x-input-label for="corporate_name" value="Razão social" />
                <x-text-input id="corporate_name" name="corporate_name" class="mt-1" required
                              :value="old('corporate_name', $company->corporate_name)" />
                <x-input-error :messages="$errors->get('corporate_name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="trade_name" value="Nome fantasia" />
                <x-text-input id="trade_name" name="trade_name" class="mt-1" :value="old('trade_name', $company->trade_name)" />
                <x-input-error :messages="$errors->get('trade_name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="cnpj" value="CNPJ" />
                <x-text-input id="cnpj" name="cnpj" class="mt-1" required x-mask="99.999.999/9999-99" placeholder="00.000.000/0000-00"
                              :value="old('cnpj', $company->cnpj ? \App\Support\Br::formatCnpj($company->cnpj) : '')" />
                <x-input-error :messages="$errors->get('cnpj')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="size" value="Porte" />
                <x-form.select id="size" name="size" class="mt-1">
                    @foreach (\App\Domain\Enums\BidCompanySize::cases() as $size)
                        <option value="{{ $size->value }}" @selected(old('size', $company->size?->value) === $size->value)>
                            {{ $size->label() }}
                        </option>
                    @endforeach
                </x-form.select>
                <p class="mt-1 text-xs text-steel">Usado como critério eliminatório em itens exclusivos ME/EPP.</p>
                <x-input-error :messages="$errors->get('size')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="color" value="Cor de identificação" />
                <input type="color" id="color" name="color" value="{{ old('color', $company->color ?: '#0a0a0a') }}"
                       class="mt-1 h-10 w-20 rounded-md border border-hairline bg-white p-1">
                <p class="mt-1 text-xs text-steel">Identifica a empresa na matriz de conformidade.</p>
            </div>
        </div>
    </div>

    {{-- Perfil — é o que permite responder "esta empresa pode participar?" --}}
    <div class="border-t border-hairline pt-5">
        <p class="text-sm font-semibold text-brand-ink mb-3">Perfil para habilitação</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <x-input-label for="capital_social" value="Capital social" />
                <x-form.money name="capital_social" class="mt-1"
                              :value="old('capital_social', $company->capital_social ? number_format((float) $company->capital_social, 2, ',', '.') : '')" />
                <x-input-error :messages="$errors->get('capital_social')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="net_worth" value="Patrimônio líquido" />
                <x-form.money name="net_worth" class="mt-1"
                              :value="old('net_worth', $company->net_worth ? number_format((float) $company->net_worth, 2, ',', '.') : '')" />
                <p class="mt-1 text-xs text-steel">Do último balanço — comparado com o mínimo exigido no edital.</p>
                <x-input-error :messages="$errors->get('net_worth')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="tax_regime" value="Regime tributário" />
                <x-text-input id="tax_regime" name="tax_regime" class="mt-1" placeholder="Simples, Presumido, Real"
                              :value="old('tax_regime', $company->tax_regime)" />
            </div>
        </div>

        {{-- CNAEs --}}
        <div class="mt-5">
            <div class="flex items-center justify-between mb-2">
                <x-input-label value="CNAEs" />
                <button type="button" @click="addCnae"
                        class="inline-flex items-center gap-1.5 rounded-md border border-hairline px-3 py-1 text-xs font-medium text-brand-ink hover:bg-surface">
                    <i class="fa-solid fa-plus"></i> Adicionar CNAE
                </button>
            </div>

            <template x-for="(cnae, index) in cnaes" :key="index">
                <div class="flex flex-col sm:flex-row gap-2 mb-2">
                    <input type="text" :name="`cnaes[${index}][code]`" x-model="cnae.code"
                           x-mask="9999-9/99" placeholder="0000-0/00"
                           class="sm:w-36 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                    <input type="text" :name="`cnaes[${index}][description]`" x-model="cnae.description"
                           placeholder="Descrição da atividade"
                           class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
                    <label class="inline-flex items-center gap-2 text-sm text-steel whitespace-nowrap px-1">
                        <input type="radio" :name="'cnae_primary'" :value="index" x-model.number="primaryIndex"
                               class="text-brand-orange focus:ring-brand-orange">
                        Principal
                    </label>
                    <input type="hidden" :name="`cnaes[${index}][primary]`" :value="primaryIndex === index ? 1 : 0">
                    <button type="button" @click="removeCnae(index)"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-md text-steel hover:bg-red-50 hover:text-red-600">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </template>
            <p class="text-xs text-steel">
                A comparação com o edital usa a classe do CNAE (5 primeiros dígitos).
            </p>
            <x-input-error :messages="$errors->get('cnaes')" class="mt-1" />
        </div>

        {{-- Ramos de atuação --}}
        <div class="mt-5">
            <x-input-label value="Ramos de atuação" />
            <p class="text-xs text-steel mb-2">
                Usados para desempate por afinidade: se o objeto do edital cita as palavras-chave do
                ramo, a empresa ganha destaque no ranking.
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach ($lines as $line)
                    <label class="flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm hover:bg-surface cursor-pointer">
                        <input type="checkbox" name="business_lines[]" value="{{ $line->id }}"
                               @checked(in_array($line->id, $selectedLines))
                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        <span class="text-brand-ink">{{ $line->name }}</span>
                    </label>
                @endforeach
            </div>
            @if ($lines->isEmpty())
                <p class="text-xs text-steel">
                    Nenhum ramo cadastrado —
                    <a href="{{ route('bid.settings.index') }}" class="text-brand-orange-deep hover:underline">cadastre em Configurações</a>.
                </p>
            @endif
        </div>
    </div>

    {{-- Contato e endereço --}}
    <div class="border-t border-hairline pt-5">
        <p class="text-sm font-semibold text-brand-ink mb-3">Contato e endereço</p>
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="responsible_name" value="Responsável" />
                <x-text-input id="responsible_name" name="responsible_name" class="mt-1" :value="old('responsible_name', $company->responsible_name)" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="email" value="E-mail" />
                <x-text-input id="email" type="email" name="email" class="mt-1" :value="old('email', $company->email)" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="phone" value="Telefone" />
                <x-text-input id="phone" name="phone" class="mt-1" x-mask:dynamic="phoneMask" placeholder="(00) 00000-0000"
                              :value="old('phone', $company->phone)" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="zipcode" value="CEP" />
                <x-text-input id="zipcode" name="zipcode" class="mt-1" x-model="zipcode" x-mask="99999-999" placeholder="00000-000"
                              @input.debounce.400ms="fetchCep" @blur="fetchCep" :value="old('zipcode', $company->zipcode)" />
            </div>
            <div class="sm:col-span-3">
                <x-input-label for="address" value="Logradouro" />
                <x-text-input id="address" name="address" x-ref="address" class="mt-1" :value="old('address', $company->address)" />
            </div>
            <div class="sm:col-span-1">
                <x-input-label for="number" value="Número" />
                <x-text-input id="number" name="number" class="mt-1" :value="old('number', $company->number)" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="complement" value="Complemento" />
                <x-text-input id="complement" name="complement" class="mt-1" :value="old('complement', $company->complement)" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="district" value="Bairro" />
                <x-text-input id="district" name="district" x-ref="district" class="mt-1" :value="old('district', $company->district)" />
            </div>
            <div class="sm:col-span-1">
                <x-input-label for="city" value="Cidade" />
                <x-text-input id="city" name="city" x-ref="city" class="mt-1" :value="old('city', $company->city)" />
            </div>
            <div class="sm:col-span-1">
                <x-input-label for="state" value="UF" />
                <x-text-input id="state" name="state" x-ref="state" class="mt-1" x-mask="aa" maxlength="2" :value="old('state', $company->state)" />
            </div>
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Observações" />
        <textarea id="notes" name="notes" rows="2"
                  class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('notes', $company->notes) }}</textarea>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $company->active ?? true))
               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Empresa ativa (participa das análises de edital)</label>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ $isEdit ? route('bid.companies.show', $company) : route('bid.companies.index') }}"
           class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>

@push('scripts')
<script>
    function bidCompanyForm(initialCnaes) {
        const rows = (initialCnaes ?? []).map((cnae) => ({
            code: cnae.code ?? '',
            description: cnae.description ?? '',
            primary: !!cnae.primary,
        }));

        return {
            cnaes: rows.length ? rows : [{ code: '', description: '', primary: true }],
            primaryIndex: Math.max(0, rows.findIndex((cnae) => cnae.primary)),
            zipcode: @json(old('zipcode', $company->zipcode)),
            lastCep: (@json(old('zipcode', $company->zipcode)) ?? '').replace(/\D/g, '') || null,

            phoneMask(input) {
                return input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999';
            },

            addCnae() {
                this.cnaes.push({ code: '', description: '', primary: false });
            },

            removeCnae(index) {
                this.cnaes.splice(index, 1);
                if (!this.cnaes.length) this.addCnae();
                if (this.primaryIndex >= this.cnaes.length) this.primaryIndex = 0;
            },

            async fetchCep() {
                const cep = (this.zipcode || '').replace(/\D/g, '');
                if (cep.length !== 8 || cep === this.lastCep) return;
                this.lastCep = cep;

                try {
                    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                    const data = await response.json();
                    if (data.erro) {
                        window.upAlerts.notifyError('CEP não encontrado.');
                        return;
                    }
                    this.$refs.address.value = data.logradouro ?? '';
                    this.$refs.district.value = data.bairro ?? '';
                    this.$refs.city.value = data.localidade ?? '';
                    this.$refs.state.value = data.uf ?? '';
                } catch (error) {
                    window.upAlerts.notifyError('Não foi possível buscar o CEP. Preencha o endereço manualmente.');
                }
            },
        };
    }
</script>
@endpush
