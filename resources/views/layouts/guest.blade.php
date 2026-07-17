<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/x-icon" href="{{ url('img/favicon-up.png')}}">

        <title>{{ config('app.name', 'upMusic') }} - Gestão de processos internos</title>
        <link rel="icon" type="image/x-icon" href="{{ url('img/favicon-up.png')}}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex bg-brand-ink">
            {{-- Painel de marca (desktop) --}}
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 relative overflow-hidden">
                <div class="relative z-10">
                    <a href="/">
                        <x-application-logo variant="branca" class="h-10" />
                    </a>
                </div>
                <div class="relative z-10">
                    <h1 class="text-3xl font-semibold text-white leading-tight">
                        Gestão de processos internos
                    </h1>
                    <p class="mt-3 text-base text-white/70 max-w-md">
                        Centralize orçamentos, contratos, financeiro e prestação de contas em um fluxo
                        visual único.
                    </p>
                </div>
                {{-- Detalhe de marca --}}
                <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-brand-orange/20 blur-3xl"></div>
                <div class="absolute top-1/3 -left-16 w-64 h-64 rounded-full bg-brand-orange/10 blur-3xl"></div>
            </div>

            {{-- Área do formulário --}}
            <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-surface">
                <div class="lg:hidden mb-8">
                    <a href="/"><x-application-logo variant="preta" class="h-10" /></a>
                </div>

                <div class="w-full sm:max-w-md bg-white shadow-sm rounded-xl border border-hairline px-8 py-8">
                    {{ $slot }}
                </div>

                <p class="mt-8 text-xs text-steel">
                    &copy; {{ date('Y') }} upMusic. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </body>
</html>
