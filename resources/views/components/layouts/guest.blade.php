@props([
    'title' => null,
    'heading' => null,
    'description' => null,
])

@php
    // Halaman error juga memakai layout ini; pengguna yang masih login diarahkan ke dasbornya.
    // rescue(): halaman 500/503 tetap tampil walau sesi atau database sedang bermasalah.
    $user = rescue(fn () => auth()->user(), null, false);
    $home = $user ? ['url' => route($user->role->dashboardRoute()), 'label' => 'Kembali ke dasbor'] : ['url' => url('/'), 'label' => 'Kembali ke beranda'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title, 'noindex' => true])
</head>
<body class="min-h-dvh bg-canvas font-sans text-ui text-ink antialiased">
    @include('partials.skip-link')
    @include('partials.test-mode')
    <main id="konten" tabindex="-1" class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">
            <a href="{{ url('/') }}" class="mb-6 flex items-center justify-center gap-3 rounded-control leading-tight">
                <x-logo />
                <span>
                    <span class="block text-card-title font-semibold text-ink">SIM-KER</span>
                    <span class="block text-caption text-ink-muted">Distrik Ransiki</span>
                </span>
            </a>

            <x-admin.card :title="$heading" :description="$description">
                {{ $slot }}
            </x-admin.card>

            @isset($footer)
                {{ $footer }}
            @else
                <p class="mt-6 text-center text-caption">
                    <a href="{{ $home['url'] }}" class="rounded-control text-ink-soft hover:text-ink">{{ $home['label'] }}</a>
                </p>
            @endisset
        </div>
    </main>
</body>
</html>
