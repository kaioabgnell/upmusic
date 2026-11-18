@php
    $isEdit = isset($bankAccount);
    $selectedEmpresas = array_map('intval', (array) old('empresas', $isEdit ? $bankAccount->empresas->pluck('id')->all() : []));
    $empresaName = fn ($e) => $e->trade_name ?: $e->corporate_name;
@endphp

<form method="POST" action="{{ $isEdit ? route('bank-accounts.update', $bankAccount) : route('bank-accounts.store') }}"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    {{-- Ordem de dependência: a empresa já precisa existir (Cadastros → Empresas). Por isso ela vem
         primeiro — sem empresa marcada, a conta não salva. --}}
    <div x-data="{
            search: '',
            selected: @js($selectedEmpresas),
            names: @js($empresas->mapWithKeys(fn ($e) => [$e->id => mb_strtolower($empresaName($e).' '.$e->corporate_name.' '.$e->document)])),
            matches(id) { return this.search === '' || this.names[id].includes(this.search.toLowerCase()) },
            get visible() { return Object.keys(this.names).filter(id => this.matches(id)).length },
         }">
        <div class="flex items-end justify-between gap-3">
            <x-input-label value="Empresas" />
            <span class="text-xs text-steel"><span x-text="selected.length"></span> selecionada(s)</span>
        </div>
        <p class="mt-0.5 text-xs text-steel">Marque todas as empresas que usam esta conta.</p>

        @if ($empresas->isEmpty())
            <div class="mt-2 rounded-md border border-dashed border-hairline p-4 text-sm text-steel">
                Nenhuma empresa ativa. <a href="{{ route('empresas.create') }}" class="text-brand-orange-deep hover:underline">Cadastre uma empresa</a> antes de criar a conta.
            </div>
        @else
            <div class="mt-2 rounded-md border border-gray-300 focus-within:border-brand-orange">
                <div class="relative border-b border-hairline">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-steel"></i>
                    <input type="text" x-model="search" placeholder="Pesquisar empresa por nome ou documento"
                           class="w-full pl-8 h-9 text-sm border-0 focus:ring-0 rounded-t-md">
                </div>
                <div class="max-h-56 overflow-y-auto p-1.5 space-y-0.5">
                    @foreach ($empresas as $empresa)
                        <label x-show="matches('{{ $empresa->id }}')"
                               class="flex items-center gap-2.5 px-2 py-1.5 rounded-md hover:bg-surface text-sm cursor-pointer">
                            <input type="checkbox" name="empresas[]" value="{{ $empresa->id }}" x-model.number="selected"
                                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                            <span class="flex-1 min-w-0">
                                <span class="block truncate text-brand-ink">{{ $empresaName($empresa) }}</span>
                                <span class="block truncate text-[11px] text-steel">
                                    {{ $empresa->document }}@unless ($empresa->active) · inativa @endunless
                                </span>
                            </span>
                        </label>
                    @endforeach
                    <p x-show="visible === 0" class="px-2 py-1.5 text-xs text-steel">Nenhuma empresa encontrada.</p>
                </div>
            </div>
        @endif
        <x-input-error :messages="array_merge($errors->get('empresas'), $errors->get('empresas.*'))" class="mt-1" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="bank" value="Banco" />
            <x-text-input id="bank" name="bank" list="banks" :value="old('bank', $isEdit ? $bankAccount->bank : '')"
                          class="mt-1" maxlength="100" required placeholder="Ex.: 341 - Itaú Unibanco" />
            <datalist id="banks">
                @foreach ($banks as $bank)
                    <option value="{{ $bank }}"></option>
                @endforeach
            </datalist>
            <x-input-error :messages="$errors->get('bank')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="account_type" value="Tipo de conta" />
            <x-form.select id="account_type" name="account_type" class="mt-1" required>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('account_type', $isEdit ? $bankAccount->account_type->value : 'corrente') === $value)>{{ $label }}</option>
                @endforeach
            </x-form.select>
            <x-input-error :messages="$errors->get('account_type')" class="mt-1" />
        </div>
    </div>

    <div>
        <x-input-label for="name" value="Nome da conta" />
        <x-text-input id="name" name="name" :value="old('name', $isEdit ? $bankAccount->name : '')"
                      class="mt-1" maxlength="120" required placeholder="Ex.: Operacional Itaú" />
        <p class="mt-1 text-xs text-steel">É o nome que aparece ao lançar pagamentos e recebimentos.</p>
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-[10rem_1fr] gap-5">
        <div>
            <x-input-label for="agency" value="Agência" />
            <x-text-input id="agency" name="agency" :value="old('agency', $isEdit ? $bankAccount->agency : '')"
                          class="mt-1" maxlength="20" required placeholder="0001" />
            <x-input-error :messages="$errors->get('agency')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="account_number" value="Conta" />
            <x-text-input id="account_number" name="account_number" :value="old('account_number', $isEdit ? $bankAccount->account_number : '')"
                          class="mt-1" maxlength="30" required placeholder="12345-6" />
            <x-input-error :messages="$errors->get('account_number')" class="mt-1" />
        </div>
    </div>

    <div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="active" name="active" value="1"
                   @checked(old('active', $isEdit ? $bankAccount->active : true))
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            <label for="active" class="text-sm text-brand-ink">Conta ativa</label>
        </div>
        <p class="mt-1 text-xs text-steel">Inativa não aparece para novos lançamentos, mas continua nos que já a usam.</p>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('bank-accounts.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
