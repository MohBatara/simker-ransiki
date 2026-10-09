@props([
    'title' => null,
    'heading' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh bg-canvas font-sans text-ui text-ink antialiased">
    <main class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">
            <a href="{{ url('/') }}" class="mb-6 block rounded-control text-center leading-tight">
                <span class="block text-card-title font-semibold text-ink">SIM-KER</span>
                <span class="block text-caption text-ink-muted">Distrik Ransiki</span>
            </a>

            <x-card :title="$heading" :description="$description">
                {{ $slot }}
            </x-card>

            <p class="mt-6 text-center text-caption">
                <a href="{{ url('/') }}" class="rounded-control text-ink-soft hover:text-ink">Kembali ke beranda</a>
            </p>
        </div>
    </main>
</body>
</html>
