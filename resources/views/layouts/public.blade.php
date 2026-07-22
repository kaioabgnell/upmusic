<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/x-icon" href="{{ url('img/favicon-up.png')}}">

        <title>{{ $title ?? config('app.name', 'UpMusic') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface text-brand-ink">
        <div class="min-h-screen flex flex-col">
            {{-- Topo com logo --}}
            <header class="bg-brand-ink">
                <div class="max-w-2xl mx-auto px-6 py-5 flex justify-center">
                    <x-application-logo variant="branca" class="h-7" />
                </div>
            </header>

            <main class="flex-1 w-full max-w-2xl mx-auto px-6 py-10">
                {{ $slot }}
            </main>

            <footer class="py-6 text-center text-xs text-steel">
                &copy; {{ date('Y') }} UpMusic
            </footer>
        </div>
    </body>
</html>
