<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white font-sans text-sm text-neutral-900 antialiased">
        <main class="mx-auto max-w-xl px-4 py-16">
            <h1 class="text-[22px] font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-2 text-neutral-600">Halaman sementara untuk memeriksa hasil setup.</p>

            <dl class="mt-8 divide-y divide-neutral-200 border-y border-neutral-200">
                <div class="flex justify-between py-3">
                    <dt>Zona waktu</dt>
                    <dd class="tabular-nums">{{ config('app.timezone') }} · {{ now()->translatedFormat('j F Y H:i') }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt>Bahasa</dt>
                    <dd>{{ app()->getLocale() }}</dd>
                </div>
                <div class="flex items-center justify-between py-3">
                    <dt>Ikon</dt>
                    <dd><x-heroicon-o-check-circle class="size-5" /></dd>
                </div>
                <div class="flex items-center justify-between py-3" x-data="{ on: false }">
                    <dt>Alpine.js</dt>
                    <dd>
                        <button type="button" class="rounded-md border border-neutral-300 px-3 py-1" x-on:click="on = !on" x-text="on ? 'Aktif' : 'Klik untuk menguji'">Alpine belum berjalan</button>
                    </dd>
                </div>
            </dl>
        </main>
    </body>
</html>
