@php
    use App\Domain\Enums\AccessModule;
    use App\Domain\Enums\AccessModuleGroup;

    $isEdit = $profile->exists;
    // Depois de um erro de validação vale o que a pessoa tinha marcado, inclusive "nada ligado".
    $hasOld = session()->hasOldInput();
    $selectedModules = $hasOld ? old('modules', []) : $profile->modules->pluck('module')->all();
    $selectedBoards = array_map('intval', $hasOld ? old('boards', []) : $profile->boards->pluck('id')->all());
    $boardIds = $boards->pluck('id')->map(fn ($id) => (int) $id)->all();
@endphp

<form method="POST"
      action="{{ $isEdit ? route('access-profiles.update', $profile) : route('access-profiles.store') }}"
      class="space-y-6 max-w-4xl"
      x-data="{
          modules: @js(array_values($selectedModules)),
          boards: @js(array_values($selectedBoards)),
          boardIds: @js($boardIds),
          get configureAll() { return this.modules.includes(@js(AccessModule::BoardsConfigure->value)) },
          hasModule(value) { return this.modules.includes(value) },
          toggleModule(value) {
              this.modules = this.hasModule(value) ? this.modules.filter(m => m !== value) : [...this.modules, value];
          },
          hasBoard(id) { return this.configureAll || this.boards.includes(id) },
          toggleBoard(id) {
              this.boards = this.boards.includes(id) ? this.boards.filter(b => b !== id) : [...this.boards, id];
          },
          setModules(values, on) {
              this.modules = on ? [...new Set([...this.modules, ...values])] : this.modules.filter(m => !values.includes(m));
          },
          setBoards(on) { this.boards = on ? [...this.boardIds] : []; },
      }">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    {{-- O estado das chaves vai no envio por estes campos ocultos: assim um quadro travado por
         "Configurar quadros" mantém o vínculo gravado para quando a chave for desligada. --}}
    <template x-for="m in modules" :key="m"><input type="hidden" name="modules[]" :value="m"></template>
    <template x-for="b in boards" :key="b"><input type="hidden" name="boards[]" :value="b"></template>

    <section class="bg-white border border-hairline rounded-xl p-6">
        <x-input-label for="name" value="Nome do perfil" />
        <x-text-input id="name" name="name" :value="old('name', $profile->name)" class="mt-1" maxlength="60"
                      required autofocus placeholder="Ex.: Comercial" />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
        <x-input-error :messages="$errors->get('modules.*')" class="mt-1" />
        <x-input-error :messages="$errors->get('boards.*')" class="mt-1" />
        <p class="text-xs text-steel mt-3">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Módulo ligado libera ver, criar, editar e excluir tudo dele. Painel, Notificações e Meu perfil
            ficam sempre liberados. Usuários e Perfis de acesso são só do Administrador.
        </p>
    </section>

    @foreach ($groups as $groupValue => $modules)
        @php
            $group = AccessModuleGroup::from($groupValue);
            $values = array_map(fn ($m) => $m->value, $modules);
            $isBoards = $group === AccessModuleGroup::Quadros;
        @endphp
        <section class="bg-white border border-hairline rounded-xl overflow-hidden">
            <header class="flex items-center justify-between gap-3 px-6 py-4 border-b border-hairline">
                <h3 class="font-semibold text-brand-ink">
                    <i class="fa-solid {{ $group->icon() }} text-brand-orange mr-2"></i>{{ $group->label() }}
                </h3>
                <div class="flex items-center gap-4 text-xs font-medium">
                    <button type="button" class="text-brand-orange-deep hover:underline"
                            x-on:click="setModules(@js($values), true){{ $isBoards ? '; setBoards(true)' : '' }}">
                        Ligar todos
                    </button>
                    <button type="button" class="text-steel hover:text-brand-ink hover:underline"
                            x-on:click="setModules(@js($values), false){{ $isBoards ? '; setBoards(false)' : '' }}">
                        Desligar todos
                    </button>
                </div>
            </header>

            <div class="divide-y divide-hairline">
                @foreach ($modules as $module)
                    <label class="flex items-center gap-4 px-6 py-3 cursor-pointer hover:bg-surface/60">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface text-steel">
                            <i class="fa-solid {{ $module->icon() }}"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium text-brand-ink">{{ $module->label() }}</span>
                            @if ($module->hint())
                                <span class="block text-xs text-steel">{{ $module->hint() }}</span>
                            @endif
                        </span>
                        {{-- Atributo de componente não compila @js(); o valor do enum é só [a-z_]. --}}
                        <x-form.toggle x-bind:checked="hasModule('{{ $module->value }}')"
                                       x-on:change="toggleModule('{{ $module->value }}')" />
                    </label>
                @endforeach

                @if ($isBoards)
                    <p class="px-6 pt-4 pb-2 text-[11px] font-semibold uppercase tracking-wider text-steel">Quadros de departamento</p>
                    @forelse ($boards as $board)
                        <label class="flex items-center gap-4 px-6 py-3"
                               :class="configureAll ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-surface/60'">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface text-steel">
                                <i class="fa-solid {{ $board->icon ?: 'fa-table-columns' }}"></i>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-brand-ink">{{ $board->name }}</span>
                                <span x-show="configureAll" x-cloak class="block text-xs text-steel">Incluído em Configurar quadros</span>
                            </span>
                            <x-form.toggle x-bind:checked="hasBoard({{ $board->id }})"
                                           x-bind:disabled="configureAll"
                                           x-on:change="toggleBoard({{ $board->id }})" />
                        </label>
                    @empty
                        <p class="px-6 py-3 text-sm text-steel">Nenhum quadro ativo cadastrado.</p>
                    @endforelse
                @endif
            </div>
        </section>
    @endforeach

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('access-profiles.index') }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            Cancelar
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-floppy-disk"></i> Salvar
        </button>
    </div>
</form>
