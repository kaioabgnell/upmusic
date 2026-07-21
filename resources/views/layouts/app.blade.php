<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'upMusic') }} - Gestão de processos internos</title>

        <link rel="icon" type="image/x-icon" href="{{ url('img/favicon-up.png')}}">

        {{-- PWA: instalável no Android, aparece na folha de compartilhamento via Web Share Target (specs/16). --}}
        <link rel="manifest" href="{{ url('manifest.webmanifest') }}">
        <meta name="theme-color" content="#ff8c1e">
        <link rel="apple-touch-icon" href="{{ url('img/pwa-192.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased text-brand-ink">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-surface">

            {{-- Overlay mobile --}}
            <div x-show="sidebarOpen" x-cloak x-transition.opacity
                 @click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

            <x-sidebar />

            {{-- Conteúdo --}}
            <div class="lg:pl-64">
                {{-- Topbar --}}
                <header class="sticky top-0 z-20 bg-white border-b border-hairline">
                    <div class="flex items-center gap-4 h-16 px-4 sm:px-6">
                        <button type="button" @click="sidebarOpen = true"
                                class="lg:hidden text-steel hover:text-brand-ink">
                            <i class="fa-solid fa-bars text-xl"></i>
                        </button>

                        <div class="flex-1 min-w-0">
                            @isset($header)
                                {{ $header }}
                            @endisset
                        </div>

                        {{-- Menu do usuário --}}
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-2 text-sm text-steel hover:text-brand-ink focus:outline-none">
                                    <x-user-avatar :user="Auth::user()" />
                                    <span class="hidden sm:block font-medium text-brand-ink">{{ Auth::user()?->name }}</span>
                                    <i class="fa-solid fa-chevron-down text-xs"></i>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    <i class="fa-solid fa-user w-5 text-center text-steel"></i> Perfil
                                </x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                        <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center text-steel"></i> Sair
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>

                {{-- Banner "Instalar app" — só o Chrome/Android dispara beforeinstallprompt e revela isto
                     (ver resources/js/pwa.js). Instalar habilita compartilhar direto do WhatsApp (specs/16). --}}
                <div id="pwa-install-banner" class="hidden bg-brand-ink text-white px-4 sm:px-6 py-2 flex items-center justify-between gap-3 text-sm">
                    <span class="min-w-0 truncate"><i class="fa-solid fa-mobile-screen-button mr-2"></i>Instale o upMusic para compartilhar arquivos direto do WhatsApp.</span>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" id="pwa-install-btn" class="rounded-md bg-brand-orange text-brand-ink px-3 py-1 font-semibold hover:bg-brand-orange-deep transition-colors">Instalar</button>
                        <button type="button" id="pwa-dismiss-btn" class="text-white/60 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <main class="p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Flash de sessão → toasts SweetAlert2 --}}
        @if (session('success'))
            <script>document.addEventListener('DOMContentLoaded', () => window.upAlerts?.notifySuccess(@json(session('success'))));</script>
        @endif
        @if (session('error'))
            <script>document.addEventListener('DOMContentLoaded', () => window.upAlerts?.notifyError(@json(session('error'))));</script>
        @endif

        @stack('scripts')
    </body>
</html>
