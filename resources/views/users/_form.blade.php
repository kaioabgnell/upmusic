@php
    $isEdit = isset($user);
    $isAdmin = (bool) old('is_admin', $isEdit ? $user->is_admin : false);
    $selectedEvents = array_map('intval', old('events', $isEdit ? $user->events->pluck('id')->all() : []));
    $eventScope = old('event_scope', $isEdit && $user->events->isNotEmpty() ? 'selected' : 'all');
@endphp

<form method="POST"
      action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}"
      x-data="{ isAdmin: @js($isAdmin), eventScope: @js($eventScope) }"
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

    {{-- Acesso (specs/28 §7.2): Administrador → Perfil de acesso → Eventos visíveis. --}}
    <div class="border-t border-hairline pt-5 space-y-5">
        <h3 class="text-sm font-semibold text-brand-ink"><i class="fa-solid fa-user-lock text-steel mr-1"></i> Acesso</h3>

        <div>
            <input type="hidden" name="is_admin" value="0">
            <label class="flex items-start gap-3 cursor-pointer">
                <x-form.toggle name="is_admin" value="1" x-model="isAdmin" />
                <span>
                    <span class="block text-sm font-medium text-brand-ink">Administrador</span>
                    <span class="block text-xs text-steel">Vê e faz tudo no sistema. Não usa perfil de acesso.</span>
                </span>
            </label>
            <x-input-error :messages="$errors->get('is_admin')" class="mt-1" />
        </div>

        <div x-show="!isAdmin" x-cloak>
            <div class="flex items-center justify-between gap-3">
                <x-input-label for="access_profile_id" value="Perfil de acesso" />
                <a href="{{ route('access-profiles.index') }}" target="_blank" rel="noopener"
                   class="text-xs font-medium text-brand-orange-deep hover:underline">
                    <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Gerenciar perfis
                </a>
            </div>
            <x-form.select id="access_profile_id" name="access_profile_id" class="mt-1" x-bind:disabled="isAdmin">
                <option value="">Selecione um perfil</option>
                @foreach ($profiles as $profile)
                    <option value="{{ $profile->id }}" @selected((string) old('access_profile_id', $isEdit ? $user->access_profile_id : '') === (string) $profile->id)>
                        {{ $profile->name }}
                    </option>
                @endforeach
            </x-form.select>
            <x-input-error :messages="$errors->get('access_profile_id')" class="mt-1" />
            @if ($profiles->isEmpty())
                <p class="text-xs text-steel mt-1">Nenhum perfil cadastrado. Crie um em Perfis de acesso antes.</p>
            @endif
        </div>

        <div x-show="!isAdmin" x-cloak>
            <x-input-label value="Eventos visíveis" />
            <p class="text-xs text-steel mb-2">Com "Somente os selecionados", o usuário vê só os cards, eventos e planilhas financeiras desses eventos. Os cards do quadro de custos fixos não são afetados.</p>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-6 mb-3">
                <label class="flex items-center gap-2 cursor-pointer text-sm text-brand-ink">
                    <input type="radio" name="event_scope" value="all" x-model="eventScope" class="border-gray-300 text-brand-orange focus:ring-brand-orange">
                    Todos os eventos
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-sm text-brand-ink">
                    <input type="radio" name="event_scope" value="selected" x-model="eventScope" class="border-gray-300 text-brand-orange focus:ring-brand-orange">
                    Somente os selecionados
                </label>
            </div>
            <div x-show="eventScope === 'selected'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                @forelse ($events as $event)
                    <label class="flex items-center gap-2 rounded-md border border-hairline px-3 py-2 cursor-pointer hover:bg-surface">
                        <input type="checkbox" name="events[]" value="{{ $event->id }}"
                               @checked(in_array($event->id, $selectedEvents, true))
                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                        <span class="text-sm text-brand-ink">
                            <i class="fa-solid fa-calendar-days text-steel mr-1"></i>
                            {{ $event->name }}
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-steel">Nenhum evento cadastrado ainda.</p>
                @endforelse
            </div>
            <x-input-error :messages="$errors->get('events')" class="mt-1" />
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
