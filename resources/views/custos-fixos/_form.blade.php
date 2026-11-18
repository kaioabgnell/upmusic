@php
    $isEdit = isset($fixedCost);
    $locked = $isEdit && $fixedCost->hasGeneratedInstallments();
    $maxGenerated = $isEdit ? $fixedCost->maxGeneratedNumber() : 0;
    $oldMode = old('generation_mode', $isEdit ? $fixedCost->generation_mode->value : \App\Domain\Enums\FixedCostGenerationMode::LeadDays->value);
    // Depois de um erro de validação, o old() volta já convertido pelo Form Request (1500.0) — reformata
    // para o padrão BR da máscara ("1.500,00"). O que a pessoa digitou sempre tem vírgula.
    $value = old('installment_value', $isEdit ? (float) $fixedCost->installment_value : '');
    if ($value !== null && $value !== '' && ! str_contains((string) $value, ',')) {
        $value = number_format((float) $value, 2, ',', '.');
    }
@endphp

{{-- Cadastro de custo fixo (specs/27 §2). A coluna depende do quadro (select dependente em Alpine), e o
     envio pede confirmação quando o modo "todas agora" vai criar vários cards de uma vez. --}}
<form method="POST" action="{{ $isEdit ? route('fixed-costs.update', $fixedCost) : route('fixed-costs.store') }}"
      x-data="fixedCostForm({
          boardColumns: {{ Illuminate\Support\Js::from($boardColumns) }},
          boardId: {{ Illuminate\Support\Js::from((string) old('board_id', $isEdit ? $fixedCost->board_id : ($boards->count() === 1 ? $boards->first()->id : ''))) }},
          columnId: {{ Illuminate\Support\Js::from((string) old('board_column_id', $isEdit ? $fixedCost->board_column_id : '')) }},
          mode: {{ Illuminate\Support\Js::from($oldMode) }},
          originalMode: {{ Illuminate\Support\Js::from($isEdit ? $fixedCost->generation_mode->value : null) }},
          total: {{ Illuminate\Support\Js::from((int) old('installments_total', $isEdit ? $fixedCost->installments_total : 12)) }},
          start: {{ Illuminate\Support\Js::from((int) old('start_installment', $isEdit ? $fixedCost->start_installment : 1)) }},
          maxGenerated: {{ $maxGenerated }},
          installmentValue: {{ Illuminate\Support\Js::from((string) $value) }},
      })"
      @submit="confirmSubmit($event)"
      class="bg-white border border-hairline rounded-xl p-6 space-y-6 max-w-3xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    {{-- O que é e de quem --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" :value="old('name', $isEdit ? $fixedCost->name : '')" class="mt-1" maxlength="120" required placeholder="Aluguel" />
            <p class="mt-1 text-xs text-steel">Vira o título de cada card gerado.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="empresa_id" value="Empresa" />
            @if ($empresas->isEmpty())
                <p class="mt-1 text-sm text-steel">
                    Nenhuma empresa ativa. <a href="{{ route('empresas.create') }}" class="text-brand-orange-deep font-medium hover:underline">Cadastre a empresa</a> antes do custo fixo.
                </p>
            @else
                <x-form.select id="empresa_id" name="empresa_id" class="mt-1" required>
                    <option value="">— Selecione —</option>
                    @foreach ($empresas as $empresa)
                        <option value="{{ $empresa->id }}" @selected(old('empresa_id', $isEdit ? $fixedCost->empresa_id : '') == $empresa->id)>
                            {{ $empresa->trade_name ?: $empresa->corporate_name }}{{ $empresa->active ? '' : ' (inativa)' }}
                        </option>
                    @endforeach
                </x-form.select>
            @endif
            <x-input-error :messages="$errors->get('empresa_id')" class="mt-1" />
        </div>
    </div>

    {{-- Parcelas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <x-input-label for="installment_value" value="Valor da parcela" />
            {{-- Mesma máscara dos valores do card e do Financeiro (money.js): dígito digitado é centavo. --}}
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-steel pointer-events-none">R$</span>
                <input type="text" id="installment_value" name="installment_value" inputmode="decimal" required
                       :value="installmentValue" @input="installmentValue = window.maskMoneyDigits($event.target.value)"
                       placeholder="0,00"
                       class="w-full pl-9 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">
            </div>
            <p class="mt-1 text-xs text-steel">Vira o valor previsto do card.</p>
            <x-input-error :messages="$errors->get('installment_value')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="installments_total" value="Quantidade de parcelas" />
            <x-text-input id="installments_total" name="installments_total" type="number" :min="max(1, $maxGenerated)" max="360" required
                          x-model.number="total" class="mt-1" />
            @if ($maxGenerated > 0)
                <p class="mt-1 text-xs text-steel">Mínimo {{ $maxGenerated }}: já existem parcelas geradas até a {{ $maxGenerated }}.</p>
            @endif
            <x-input-error :messages="$errors->get('installments_total')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="start_installment" value="Começar na parcela" />
            <x-text-input id="start_installment" name="start_installment" type="number" min="1" max="360"
                          x-model.number="start" class="mt-1" :disabled="$locked"
                          title="{{ $locked ? 'Travado depois da primeira parcela gerada.' : '' }}" />
            <p class="mt-1 text-xs text-steel">{{ $locked ? 'Travado depois da primeira parcela gerada.' : 'Para custo já em andamento.' }}</p>
            <x-input-error :messages="$errors->get('start_installment')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="first_due_date" value="Primeiro vencimento" />
            <x-text-input id="first_due_date" name="first_due_date" type="date" required class="mt-1" :disabled="$locked"
                          :value="old('first_due_date', $isEdit ? $fixedCost->first_due_date->toDateString() : '')"
                          title="{{ $locked ? 'Travado depois da primeira parcela gerada.' : '' }}" />
            <p class="mt-1 text-xs text-steel">Vencimento da parcela <span x-text="start || 1"></span>. As seguintes caem no mesmo dia de cada mês.</p>
            <x-input-error :messages="$errors->get('first_due_date')" class="mt-1" />
        </div>
    </div>

    {{-- Destino --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="board_id" value="Quadro de destino" />
            @if ($boards->isEmpty())
                <p class="mt-1 text-sm text-steel">
                    Marque um quadro como "Quadro de custos fixos" antes de cadastrar.
                    <a href="{{ route('boards.index') }}" class="text-brand-orange-deep font-medium hover:underline">Ir para os quadros</a>
                </p>
            @else
                <x-form.select id="board_id" name="board_id" class="mt-1" required x-model="boardId" @change="columnId = ''">
                    <option value="">— Selecione —</option>
                    @foreach ($boards as $board)
                        <option value="{{ $board->id }}">{{ $board->name }}</option>
                    @endforeach
                </x-form.select>
            @endif
            <x-input-error :messages="$errors->get('board_id')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="board_column_id" value="Coluna de destino" />
            <x-form.select id="board_column_id" name="board_column_id" class="mt-1" x-model="columnId" ::disabled="!boardId">
                <option value="">Coluna de entrada do quadro</option>
                <template x-for="col in columns" :key="col.id">
                    <option :value="String(col.id)" x-text="col.name + (col.is_entry ? ' (entrada)' : '')" :selected="String(col.id) === columnId"></option>
                </template>
            </x-form.select>
            @if ($isEdit && $locked)
                <p class="mt-1 text-xs text-steel">Quadro e coluna novos valem só para as próximas parcelas.</p>
            @endif
            <x-input-error :messages="$errors->get('board_column_id')" class="mt-1" />
        </div>
    </div>

    {{-- Modo de geração --}}
    <div>
        <x-input-label value="Geração dos cards" />
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($modes as $mode)
                <label class="flex items-start gap-3 rounded-lg border px-4 py-3 cursor-pointer transition-colors"
                       :class="mode === '{{ $mode->value }}' ? 'border-brand-orange bg-brand-orange/5' : 'border-hairline hover:bg-surface'">
                    <input type="radio" name="generation_mode" value="{{ $mode->value }}" x-model="mode"
                           class="mt-0.5 border-gray-300 text-brand-orange focus:ring-brand-orange">
                    <span>
                        <span class="block text-sm font-medium text-brand-ink">{{ $mode->label() }}</span>
                        <span class="block text-xs text-steel mt-0.5">
                            @if ($mode === \App\Domain\Enums\FixedCostGenerationMode::AllUpfront)
                                Todos os cards nascem ao salvar, cada um com o seu vencimento.
                            @else
                                Um comando diário cria cada card no dia certo. O quadro fica só com o que está perto de vencer.
                            @endif
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('generation_mode')" class="mt-1" />

        {{-- Campo compacto, em linha com o texto. Input cru (não <x-text-input>): o componente força
             w-full e anularia a largura fixa. --}}
        <div x-show="mode === 'lead_days'" x-cloak
             class="mt-3 inline-flex items-center gap-2 rounded-md border border-hairline bg-surface px-3 py-2">
            <label for="lead_days" class="text-sm text-steel">Criar o card</label>
            <input type="number" id="lead_days" name="lead_days" min="0" max="60" step="1"
                   value="{{ old('lead_days', $isEdit && $fixedCost->lead_days !== null ? $fixedCost->lead_days : 10) }}"
                   class="w-16 h-8 px-2 text-center text-sm border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm">
            <span class="text-sm text-steel">dias antes do vencimento</span>
        </div>
        <x-input-error :messages="$errors->get('lead_days')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="description" value="Descrição" />
        <textarea id="description" name="description" rows="3" maxlength="2000"
                  class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('description', $isEdit ? $fixedCost->description : '') }}</textarea>
        <p class="mt-1 text-xs text-steel">Copiada para a descrição de cada card.</p>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    <div class="space-y-3">
        <div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" id="active" name="active" value="1"
                       @checked(old('active', $isEdit ? $fixedCost->active : true))
                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                <label for="active" class="text-sm text-brand-ink">Custo fixo ativo</label>
            </div>
            <p class="mt-1 text-xs text-steel">Inativo não gera parcelas novas. Os cards já gerados não mudam.</p>
        </div>

        @if ($isEdit && $locked)
            <div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="apply_to_generated" value="0">
                    <input type="checkbox" id="apply_to_generated" name="apply_to_generated" value="1"
                           @checked(old('apply_to_generated'))
                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                    <label for="apply_to_generated" class="text-sm text-brand-ink">Aplicar também às parcelas já geradas e não concluídas</label>
                </div>
                <p class="mt-1 text-xs text-steel">Atualiza nome, descrição, empresa e valor previsto dos cards em aberto (nem concluídos, nem arquivados).</p>
            </div>
        @endif
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ $isEdit ? route('fixed-costs.show', $fixedCost) : route('fixed-costs.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>

@push('scripts')
<script>
    function fixedCostForm(cfg) {
        return {
            boardColumns: cfg.boardColumns,
            boardId: cfg.boardId,
            columnId: cfg.columnId,
            mode: cfg.mode,
            originalMode: cfg.originalMode,
            total: cfg.total,
            start: cfg.start,
            maxGenerated: cfg.maxGenerated,
            installmentValue: cfg.installmentValue,
            confirmed: false,

            get columns() {
                return this.boardColumns[this.boardId] || [];
            },

            // Parcelas ainda sem card: da maior já gerada (ou de "começar na parcela") até o total.
            get pendingCount() {
                const covered = Math.max(this.maxGenerated, (Number(this.start) || 1) - 1);
                return Math.max(0, (Number(this.total) || 0) - covered);
            },

            // "Todas agora" cria os cards na hora: confirma quando entra nesse modo (cadastro novo ou
            // troca de modo) e há parcelas para criar. Ver specs/27 §5.1.
            async confirmSubmit(event) {
                if (this.confirmed || this.mode !== 'all_upfront' || this.originalMode === 'all_upfront' || this.pendingCount === 0) return;
                event.preventDefault();
                const ok = await window.upAlerts.confirmAction({
                    title: 'Gerar todas as parcelas',
                    text: `Isso vai criar ${this.pendingCount} card(s) agora.`,
                    confirmText: 'Gerar',
                    icon: 'question',
                });
                if (ok) {
                    this.confirmed = true;
                    event.target.submit();
                }
            },
        };
    }
</script>
@endpush
