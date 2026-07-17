@php $isEdit = isset($event); @endphp

<form method="POST" action="{{ $isEdit ? route('eventos.update', $event) : route('eventos.store') }}"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <x-input-label for="name" value="Nome do evento" />
        <x-text-input id="name" name="name" :value="old('name', $isEdit ? $event->name : '')" class="mt-1" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="location" value="Local do evento" />
        <x-text-input id="location" name="location" :value="old('location', $isEdit ? $event->location : '')" class="mt-1" />
        <x-input-error :messages="$errors->get('location')" class="mt-1" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="responsible_name" value="Responsável" />
            <x-text-input id="responsible_name" name="responsible_name" :value="old('responsible_name', $isEdit ? $event->responsible_name : '')" class="mt-1" />
            <x-input-error :messages="$errors->get('responsible_name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="phone" value="Telefone" />
            <x-text-input id="phone" name="phone" :value="old('phone', $isEdit ? $event->phone : '')" class="mt-1"
                          x-data x-mask:dynamic="$input.replace(/\D/g,'').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999'" placeholder="(00) 00000-0000" />
            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email', $isEdit ? $event->email : '')" class="mt-1" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 border-t border-hairline pt-5">
        <div>
            <x-input-label for="start_date" value="Data de início do evento" />
            <x-text-input id="start_date" type="date" name="start_date" :value="old('start_date', $isEdit ? $event->start_date?->format('Y-m-d') : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="end_date" value="Data de fim do evento" />
            <x-text-input id="end_date" type="date" name="end_date" :value="old('end_date', $isEdit ? $event->end_date?->format('Y-m-d') : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $isEdit ? $event->active : true)) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
        <label for="active" class="text-sm text-brand-ink">Evento ativo</label>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('eventos.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
