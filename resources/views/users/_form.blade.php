@php
    $isEdit = isset($user);
    $coordenador = auth()->user()->isCoordenador();
    $selectedRole = old('role', $isEdit ? $user->role->value : 'usuario');
    $selectedBoards = old('boards', $isEdit ? $user->boards->pluck('id')->all() : []);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}"
      x-data="{ role: '{{ $selectedRole }}' }"
      class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" :value="old('name', $isEdit ? $user->name : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email', $isEdit ? $user->email : '')" class="mt-1" required />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="role" value="Perfil" />
            <x-form.select id="role" name="role" class="mt-1" x-model="role" :disabled="$coordenador">
                @foreach ($roles as $value => $label)
                    {{-- Coordenador só cria/edita usuários comuns --}}
                    @if (! $coordenador || $value === 'usuario')
                        <option value="{{ $value }}" @selected($selectedRole === $value)>{{ $label }}</option>
                    @endif
                @endforeach
            </x-form.select>
            @if ($coordenador)
                <input type="hidden" name="role" value="usuario">
            @endif
            <x-input-error :messages="$errors->get('role')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="setor_id" value="Setor" />
            <x-form.select id="setor_id" name="setor_id" class="mt-1">
                <option value="">— Sem setor —</option>
                @foreach ($setores as $setor)
                    <option value="{{ $setor->id }}" @selected((string) old('setor_id', $isEdit ? $user->setor_id : '') === (string) $setor->id)>
                        {{ $setor->nome }}
                    </option>
                @endforeach
            </x-form.select>
            <x-input-error :messages="$errors->get('setor_id')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="phone" value="Telefone" />
            <x-text-input id="phone" name="phone" :value="old('phone', $isEdit ? $user->phone : '')" class="mt-1" />
            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
        </div>

        <div class="flex items-center gap-2 pt-7">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" id="active" name="active" value="1"
                   @checked(old('active', $isEdit ? $user->active : true))
                   class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
            <label for="active" class="text-sm text-brand-ink">Usuário ativo</label>
        </div>
    </div>

    {{-- Senha --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 border-t border-hairline pt-5">
        <div>
            <x-input-label for="password" :value="$isEdit ? 'Nova senha (opcional)' : 'Senha'" />
            <x-text-input id="password" type="password" name="password" class="mt-1" :required="! $isEdit" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirmar senha" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" class="mt-1" :required="! $isEdit" autocomplete="new-password" />
        </div>
    </div>

    {{-- Vínculo de quadros (somente perfil Usuário) --}}
    <div x-show="role === 'usuario'" x-cloak class="border-t border-hairline pt-5">
        <x-input-label value="Quadros com acesso" />
        <p class="text-xs text-steel mb-2">Selecione os quadros que este usuário poderá operar.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @forelse ($boards as $board)
                <label class="flex items-center gap-2 rounded-md border border-hairline px-3 py-2 cursor-pointer hover:bg-surface">
                    <input type="checkbox" name="boards[]" value="{{ $board->id }}"
                           @checked(in_array($board->id, $selectedBoards))
                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                    <span class="text-sm text-brand-ink">
                        <i class="fa-solid {{ $board->icon ?? 'fa-table-columns' }} text-steel mr-1"></i>
                        {{ $board->name }}
                    </span>
                </label>
            @empty
                <p class="text-sm text-steel">Nenhum quadro cadastrado ainda.</p>
            @endforelse
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-hairline pt-5">
        <a href="{{ route('users.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            Cancelar
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
