<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-brand-ink">Redefinir senha</h2>
        <p class="text-sm text-steel mt-1">Escolha uma nova senha para sua conta.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="block mt-1" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password" value="Nova senha" />
            <x-text-input id="password" class="block mt-1" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar senha" />
            <x-text-input id="password_confirmation" class="block mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-key"></i> Redefinir senha
        </button>
    </form>
</x-guest-layout>
