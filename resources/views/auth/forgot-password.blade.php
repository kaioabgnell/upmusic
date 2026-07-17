<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-brand-ink">Recuperar senha</h2>
        <p class="text-sm text-steel mt-1">Informe seu e-mail e enviaremos um link para redefinir a senha.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="block mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
            <i class="fa-solid fa-paper-plane"></i> Enviar link de redefinição
        </button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="text-brand-orange-deep hover:underline">Voltar ao login</a>
        </p>
    </form>
</x-guest-layout>
