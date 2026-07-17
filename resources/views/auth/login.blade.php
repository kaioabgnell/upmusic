<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-brand-ink">Acessar o sistema</h2>
        <p class="text-sm text-steel mt-1">Entre com suas credenciais para continuar.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="block mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="voce@upmusic.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" class="block mt-1" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-orange shadow-sm focus:ring-brand-orange" name="remember">
                <span class="ms-2 text-sm text-steel">Lembrar-me</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-brand-orange-deep hover:underline" href="{{ route('password.request') }}">
                    Esqueci minha senha
                </a>
            @endif
        </div>

        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Entrar
        </button>
    </form>
</x-guest-layout>
