@props([
    'title' => null,
])

@php
    $menu = [
        ['label' => 'Beranda', 'href' => url('/'), 'active' => request()->is('/')],
        ['label' => 'Profil Ransiki', 'href' => url('/profil'), 'active' => request()->is('profil')],
        ['label' => 'Direktori Kelompok', 'href' => url('/direktori'), 'active' => request()->is('direktori*')],
        ['label' => 'Produk Unggulan', 'href' => url('/produk'), 'active' => request()->is('produk*')],
        ['label' => 'Kontak', 'href' => url('/kontak'), 'active' => request()->is('kontak')],
    ];
    $account = auth()->check()
        ? ['label' => 'Dasbor', 'href' => route(auth()->user()->role->dashboardRoute())]
        : ['label' => 'Masuk', 'href' => route('login')];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="flex min-h-dvh flex-col bg-canvas font-sans text-ui text-ink antialiased">
    <header x-data="{ open: false }" class="border-b border-line bg-paper pt-[env(safe-area-inset-top)]">
        <div class="mx-auto flex h-16 max-w-public items-center justify-between gap-6 px-4 md:px-6">
            <a href="{{ url('/') }}" class="rounded-control leading-tight">
                <span class="block text-ui font-semibold text-ink">SIM-KER</span>
                <span class="block text-caption text-ink-muted">Distrik Ransiki</span>
            </a>

            <nav aria-label="Menu utama" class="hidden items-center gap-1 lg:flex">
                @foreach ($menu as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if ($item['active']) aria-current="page" @endif
                        @class([
                            'rounded-control px-3 py-2 transition-colors',
                            'font-semibold text-brand' => $item['active'],
                            'text-ink-soft hover:text-ink' => ! $item['active'],
                        ])
                    >{{ $item['label'] }}</a>
                @endforeach
                <x-button variant="secondary" :href="$account['href']" class="ml-3">{{ $account['label'] }}</x-button>
            </nav>

            <button
                type="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open"
                aria-controls="menu-publik"
                class="-mr-2 flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink lg:hidden"
            >
                <span class="sr-only">Menu</span>
                <x-heroicon-o-bars-3 x-show="! open" class="size-6" />
                <x-heroicon-o-x-mark x-cloak x-show="open" class="size-6" />
            </button>
        </div>

        <nav id="menu-publik" aria-label="Menu utama" x-cloak x-show="open" class="border-t border-line lg:hidden">
            <ul class="mx-auto max-w-public px-4 py-2 md:px-6">
                @foreach ($menu as $item)
                    <li>
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            @class([
                                'flex h-12 items-center text-body',
                                'font-semibold text-brand' => $item['active'],
                                'text-ink' => ! $item['active'],
                            ])
                        >{{ $item['label'] }}</a>
                    </li>
                @endforeach
                <li class="pb-2 pt-2">
                    <x-button variant="secondary" :href="$account['href']" class="w-full">{{ $account['label'] }}</x-button>
                </li>
            </ul>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-public flex-1 px-4 py-8 md:px-6 md:py-12">
        {{ $slot }}
    </main>

    <footer class="border-t border-line bg-paper">
        <div class="mx-auto flex max-w-public flex-col gap-2 px-4 py-8 text-caption text-ink-soft md:flex-row md:justify-between md:px-6">
            <p>Sistem Informasi dan Monitoring Kelembagaan Ekonomi Distrik Ransiki</p>
            <p>&copy; {{ now()->year }} Distrik Ransiki</p>
        </div>
    </footer>
</body>
</html>
