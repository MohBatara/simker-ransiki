@props([
    'title' => null,
    // Ringkasan 1–2 kalimat untuk mesin pencari dan pratinjau tautan (WhatsApp).
    'description' => 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.',
    // URL absolut gambar pratinjau tautan; kosong = gambar bawaan.
    'image' => null,
    'canonical' => null,
])

@php
    $menu = [
        ['label' => 'Beranda', 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => 'Profil Ransiki', 'href' => route('public.profile'), 'active' => request()->routeIs('public.profile')],
        ['label' => 'Direktori Kelompok', 'href' => route('public.directory'), 'active' => request()->routeIs('public.directory', 'public.organization')],
        ['label' => 'Produk Unggulan', 'href' => route('public.products'), 'active' => request()->routeIs('public.products')],
        ['label' => 'Kontak', 'href' => route('public.contact'), 'active' => request()->routeIs('public.contact')],
    ];
    $account = auth()->check()
        ? ['label' => 'Dasbor', 'href' => route(auth()->user()->role->dashboardRoute())]
        : ['label' => 'Masuk', 'href' => route('login')];
    // $footer disiapkan view composer di AppServiceProvider (kontak kantor + daftar OPD, di-cache).
    $office = $footer['settings'];
    $pageTitle = $title ? $title.' · '.config('app.name') : config('app.name');
    $canonical ??= url()->current();
    $image ??= asset('og-default.png');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:card" content="summary_large_image">
</head>
<body class="flex min-h-dvh flex-col bg-canvas font-sans text-ui text-ink antialiased">
    @include('partials.skip-link')

    <header x-data="{ open: false }" class="border-b border-line bg-paper pt-[env(safe-area-inset-top)]">
        <div class="mx-auto flex h-16 max-w-public items-center justify-between gap-6 px-4 md:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-control leading-tight">
                <x-logo />
                <span>
                    <span class="block text-ui font-semibold text-ink">SIM-KER</span>
                    <span class="block text-caption text-ink-muted">Distrik Ransiki</span>
                </span>
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
                <span class="mx-2 h-5 w-px bg-line" aria-hidden="true"></span>
                <a href="{{ $account['href'] }}" class="rounded-control px-3 py-2 text-ink-soft transition-colors hover:text-ink">{{ $account['label'] }}</a>
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
                <li class="border-t border-line">
                    <a href="{{ $account['href'] }}" class="flex h-12 items-center text-body text-ink-soft">{{ $account['label'] }}</a>
                </li>
            </ul>
        </nav>
    </header>

    {{-- Isi halaman lebar penuh; setiap bagian memakai wadah max-w-public sendiri. --}}
    <main id="konten" tabindex="-1" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="on-dark bg-forest text-forest-soft">
        <div class="mx-auto grid max-w-public gap-10 px-4 py-12 md:grid-cols-3 md:px-6">
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    {{-- Tempat logo resmi: ganti isi komponen x-logo. --}}
                    <span class="rounded-control bg-paper"><x-logo /></span>
                    <div class="leading-tight">
                        <p class="font-semibold text-paper">SIM-KER Distrik Ransiki</p>
                        <p class="text-caption">Sistem Informasi dan Monitoring Kelembagaan Ekonomi</p>
                    </div>
                </div>
                <p class="text-caption">Dikelola oleh Pemerintah Distrik Ransiki.</p>
            </div>

            <div>
                <h2 class="text-ui font-semibold text-paper">Kantor Distrik Ransiki</h2>
                <dl class="mt-3 space-y-1.5 text-caption">
                    @if ($office['office_address'])
                        <div><dt class="sr-only">Alamat</dt><dd class="whitespace-pre-line">{{ $office['office_address'] }}</dd></div>
                    @endif
                    @if ($office['office_phone'])
                        <div><dt class="inline">Telepon: </dt><dd class="inline"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $office['office_phone']) }}" class="rounded-control text-sun hover:underline">{{ $office['office_phone'] }}</a></dd></div>
                    @endif
                    @if ($office['office_email'])
                        <div><dt class="inline">Email: </dt><dd class="inline"><a href="mailto:{{ $office['office_email'] }}" class="rounded-control text-sun hover:underline">{{ $office['office_email'] }}</a></dd></div>
                    @endif
                    @if ($office['office_hours'])
                        <div><dt class="inline">Jam layanan: </dt><dd class="inline">{{ $office['office_hours'] }}</dd></div>
                    @endif
                    @unless ($office['office_address'] || $office['office_phone'] || $office['office_email'])
                        <div><dd>Kontak kantor sedang disiapkan.</dd></div>
                    @endunless
                </dl>
                <a href="{{ route('public.contact') }}" class="mt-3 inline-block rounded-control text-caption font-semibold text-sun hover:underline">Semua kontak</a>
            </div>

            <div>
                <h2 class="text-ui font-semibold text-paper">OPD terkait</h2>
                <ul class="mt-3 space-y-1.5 text-caption">
                    @foreach ($footer['opds'] as $opd)
                        <li>{{ $opd }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="border-t border-forest-line">
            <div class="mx-auto flex max-w-public flex-wrap justify-between gap-2 px-4 py-4 text-caption md:px-6">
                <p>&copy; {{ now()->year }} Distrik Ransiki</p>
                <a href="{{ route('login') }}" class="rounded-control hover:text-paper">Masuk untuk pengelola</a>
            </div>
        </div>
    </footer>
</body>
</html>
