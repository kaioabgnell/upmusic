<section x-data="{ preview: null }">
    <header>
        <h2 class="text-lg font-semibold text-brand-ink">Foto de perfil</h2>
        <p class="mt-1 text-sm text-steel">Uma foto ajuda a equipe a te reconhecer mais rápido. Formatos aceitos: JPG, PNG ou WEBP, até 2MB.</p>
    </header>

    <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="mt-5 flex items-center gap-5">
        @csrf

        <template x-if="preview">
            <img :src="preview" class="w-20 h-20 rounded-full object-cover border border-hairline" alt="Pré-visualização">
        </template>
        <template x-if="!preview">
            <x-user-avatar :user="$user" size="w-20 h-20 text-2xl" />
        </template>

        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <label class="inline-flex items-center gap-2 rounded-md border border-dashed border-hairline px-3 py-1.5 text-xs font-medium text-steel cursor-pointer hover:border-brand-orange">
                    <i class="fa-solid fa-camera"></i> Escolher foto
                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="hidden"
                           @change="
                                const file = $event.target.files[0];
                                if (!file) { preview = null; return; }
                                const reader = new FileReader();
                                reader.onload = (e) => { preview = e.target.result; };
                                reader.readAsDataURL(file);
                           ">
                </label>
                <button type="submit" class="rounded-md bg-brand-orange px-3 py-1.5 text-xs font-semibold text-brand-ink hover:bg-brand-orange-deep">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar foto
                </button>
            </div>
            <x-input-error :messages="$errors->get('avatar')" />
        </div>
    </form>

    @if ($user->avatar_path)
        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-3" data-confirm="Remover sua foto de perfil?">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-steel hover:text-red-600"><i class="fa-solid fa-trash mr-1"></i>Remover foto</button>
        </form>
    @endif

    @if (session('status') === 'avatar-updated')
        <p class="mt-3 text-sm text-green-600">Foto atualizada com sucesso.</p>
    @elseif (session('status') === 'avatar-removed')
        <p class="mt-3 text-sm text-steel">Foto removida.</p>
    @endif
</section>
