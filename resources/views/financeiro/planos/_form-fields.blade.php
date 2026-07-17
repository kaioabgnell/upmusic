@php $isEdit = isset($plan); $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez']; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <div class="sm:col-span-2">
        <x-input-label for="name" value="Nome do plano" />
        <x-text-input id="name" name="name" :value="old('name', $isEdit ? $plan->name : '')" class="mt-1" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="empresa_id" value="Empresa (opcional)" />
        <x-form.select id="empresa_id" name="empresa_id" class="mt-1">
            <option value="">— Sem empresa —</option>
            @foreach ($empresas as $e)
                <option value="{{ $e->id }}" @selected((string) old('empresa_id', $isEdit ? $plan->empresa_id : '') === (string) $e->id)>{{ $e->corporate_name }}</option>
            @endforeach
        </x-form.select>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <x-input-label for="period_month" value="Mês" />
            <x-form.select id="period_month" name="period_month" class="mt-1">
                <option value="">—</option>
                @foreach ($meses as $n => $m)
                    <option value="{{ $n }}" @selected((string) old('period_month', $isEdit ? $plan->period_month : '') === (string) $n)>{{ $m }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div>
            <x-input-label for="period_year" value="Ano" />
            <x-text-input id="period_year" name="period_year" type="number" :value="old('period_year', $isEdit ? $plan->period_year : '')" class="mt-1" placeholder="2026" />
        </div>
    </div>
    <div class="sm:col-span-2">
        <x-input-label for="notes" value="Observações" />
        <textarea id="notes" name="notes" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('notes', $isEdit ? $plan->notes : '') }}</textarea>
    </div>
</div>
