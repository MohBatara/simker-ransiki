@props([
    'title' => null,
    // Foto sampul dari Konten Website (App\Support\PublicImages::slot('sampul')); null = panel forest polos.
    'cover' => null,
    'coverCredit' => null,
])

{{--
    Halaman masuk. Desktop: panel kiri forest (foto sampul + overlay padat 70%) dan form di kanan.
    HP: pita forest ringkas di atas, form di bawah. Satu-satunya pemakaian token forest di luar website
    publik (CLAUDE.md Bagian 7).
--}}
@php
    // forest-soft di atas foto berlapis 70% bisa turun ke ~3:1 bila fotonya terang; di atas foto pakai putih.
    $soft = $cover ? 'text-paper' : 'text-forest-soft';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title, 'noindex' => true])
</head>
<body class="min-h-dvh bg-canvas font-sans text-ui text-ink antialiased">
    @include('partials.skip-link')
    <div class="min-h-dvh md:grid md:grid-cols-2">
        <aside class="on-dark relative isolate flex flex-col overflow-hidden bg-forest px-4 py-6 text-paper md:min-h-dvh md:justify-between md:px-10 md:py-10">
            @if ($cover)
                <x-public.picture :image="$cover" alt="" sizes="(min-width: 48rem) 50vw, 100vw" width="960" height="1200" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
                {{-- Overlay padat 70%: teks bisa berada di mana saja di panel, kontrasnya tetap terjaga. --}}
                <div class="absolute inset-0 -z-10 bg-forest/70" aria-hidden="true"></div>
            @endif

            <a href="{{ url('/') }}" class="flex items-center gap-3 self-start rounded-control leading-tight">
                <x-logo inverse />
                <span>
                    <span class="block text-ui font-semibold">SIM-KER</span>
                    <span class="block text-caption {{ $soft }}">Distrik Ransiki</span>
                </span>
            </a>

            <p class="mt-4 max-w-md text-body md:mt-0 md:text-card-title">
                Sistem informasi untuk mendata, melaporkan, dan memantau kelembagaan ekonomi di Distrik Ransiki.
            </p>

            @if ($cover && $coverCredit)
                <p class="mt-4 text-caption text-paper max-md:hidden">Foto: {{ $coverCredit }}</p>
            @else
                <span class="max-md:hidden" aria-hidden="true"></span>
            @endif
        </aside>

        <main id="konten" tabindex="-1" class="flex items-start justify-center px-4 py-8 md:items-center md:px-10">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
