@props([
    'title' => null,
    // Ringkasan 1–2 kalimat untuk mesin pencari dan pratinjau tautan (WhatsApp).
    'description' => 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.',
    // URL absolut gambar pratinjau tautan; kosong = gambar bawaan.
    'image' => null,
    'canonical' => null,
    // Open Graph og:type: "website", atau "article" untuk halaman berita.
    'type' => 'website',
])

@php
    $menu = [
        ['label' => 'Beranda', 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => 'Profil Ransiki', 'href' => route('public.profile'), 'active' => request()->routeIs('public.profile')],
        ['label' => 'Direktori', 'href' => route('public.directory'), 'active' => request()->routeIs('public.directory', 'public.organization')],
        ['label' => 'Produk Unggulan', 'href' => route('public.products'), 'active' => request()->routeIs('public.products')],
        ['label' => 'Berita', 'href' => route('public.posts'), 'active' => request()->routeIs('public.posts', 'public.post')],
        ['label' => 'Kontak', 'href' => route('public.contact'), 'active' => request()->routeIs('public.contact')],
    ];
    $account = auth()->check()
        ? ['label' => 'Dasbor', 'href' => route(auth()->user()->role->dashboardRoute())]
        : ['label' => 'Masuk', 'href' => route('login')];
    // $footer disiapkan view composer di AppServiceProvider (kontak kantor, di-cache).
    $office = $footer['settings'];
    $tel = fn (string $phone) => 'tel:'.preg_replace('/[^0-9+]/', '', $phone);
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
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $image }}">
    {{ $meta ?? '' }}
    <meta name="twitter:card" content="summary_large_image">
</head>
<body class="flex min-h-dvh flex-col bg-canvas font-sans text-ui text-ink antialiased">
    @include('partials.skip-link')
    @include('partials.test-mode')

    <header
        x-data="{ open: false, search: false }"
        x-on:keydown.escape="search = false; open = false"
        class="sticky top-0 z-40 border-b border-line bg-paper pt-[env(safe-area-inset-top)] print:hidden"
    >
        <div class="mx-auto flex h-16 max-w-public items-center justify-between gap-6 px-4 md:h-18 md:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-control leading-tight">
                <x-logo />
                <span>
                    <span class="block font-serif text-card-title font-semibold text-ink">SIM-KER</span>
                    <span class="block text-caption text-ink-soft">Distrik Ransiki</span>
                </span>
            </a>

            <div class="flex items-center gap-1">
                <nav aria-label="Menu utama" class="hidden self-stretch lg:flex">
                    @foreach ($menu as $item)
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            @class([
                                'flex h-18 items-center border-b-2 px-3 transition-colors',
                                'border-brand font-semibold text-brand' => $item['active'],
                                'border-transparent text-ink-soft hover:text-ink' => ! $item['active'],
                            ])
                        >{{ $item['label'] }}</a>
                    @endforeach
                </nav>
                <span class="mx-2 hidden h-6 w-px bg-line lg:block" aria-hidden="true"></span>
                <button
                    type="button"
                    x-on:click="search = ! search; open = false; search && $nextTick(() => $refs.q.focus())"
                    x-bind:aria-expanded="search"
                    aria-controls="cari-publik"
                    class="flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink"
                >
                    <span class="sr-only">Cari kelompok</span>
                    <x-heroicon-o-magnifying-glass class="size-5" />
                </button>
                {{-- Masuk/Dasbor di header mulai tablet; di HP ada di dalam menu. --}}
                <x-public.button :href="$account['href']" size="md" class="ml-2 max-md:hidden">{{ $account['label'] }}</x-public.button>
                <button
                    type="button"
                    x-on:click="open = ! open; search = false"
                    x-bind:aria-expanded="open"
                    aria-controls="menu-publik"
                    class="-mr-2 flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink lg:hidden"
                >
                    <span class="sr-only">Menu</span>
                    <x-heroicon-o-bars-3 x-show="! open" class="size-6" />
                    <x-heroicon-o-x-mark x-cloak x-show="open" class="size-6" />
                </button>
            </div>
        </div>

        {{-- Pencarian direktori dari header (semua ukuran layar). --}}
        <div id="cari-publik" x-cloak x-show="search" class="border-t border-line bg-paper">
            <form method="GET" action="{{ route('public.directory') }}" role="search" class="mx-auto flex max-w-public gap-2 px-4 py-3 md:px-6">
                <label for="cari-header" class="sr-only">Cari nama kelompok atau produk</label>
                <x-input id="cari-header" name="q" x-ref="q" placeholder="Cari nama kelompok atau produk" class="min-w-0 flex-1" />
                <x-public.button type="submit" variant="brand" icon="magnifying-glass">Cari</x-public.button>
            </form>
        </div>

        <nav id="menu-publik" aria-label="Menu utama" x-cloak x-show="open" class="border-t border-line bg-paper lg:hidden">
            <ul class="mx-auto max-w-public px-4 py-2 md:px-6">
                @foreach ($menu as $item)
                    <li>
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            @class([
                                'flex h-12 items-center border-l-2 pl-3 text-body',
                                'border-brand font-semibold text-brand' => $item['active'],
                                'border-transparent text-ink' => ! $item['active'],
                            ])
                        >{{ $item['label'] }}</a>
                    </li>
                @endforeach
                <li class="mt-1 border-t border-line pt-3 pb-1 md:hidden">
                    <x-public.button :href="$account['href']" class="w-full">{{ $account['label'] }}</x-public.button>
                </li>
            </ul>
        </nav>
    </header>

    {{-- Isi halaman lebar penuh; setiap bagian memakai wadah max-w-public sendiri. --}}
    <main id="konten" tabindex="-1" class="flex-1">
        {{ $slot }}
    </main>

    @php
        $footerLinks = [
            'Tautan cepat' => [
                ['Beranda', route('home')],
                ['Profil Ransiki', route('public.profile')],
                ['Kontak', route('public.contact')],
            ],
            'Informasi' => [
                ['Direktori kelompok', route('public.directory')],
                ['Produk unggulan', route('public.products')],
                ['Berita & kegiatan', route('public.posts')],
                [$account['label'], $account['href']],
            ],
        ];
    @endphp
    <footer class="on-dark bg-forest text-forest-soft print:hidden">
        <div class="mx-auto grid max-w-public gap-10 px-4 py-12 sm:grid-cols-2 md:px-6 lg:grid-cols-[1.4fr_1fr_1fr_1.4fr]">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    {{-- Tempat logo resmi: ganti isi komponen x-logo. --}}
                    <x-logo inverse />
                    <div class="leading-tight">
                        <p class="font-serif text-card-title font-semibold text-paper">SIM-KER</p>
                        <p class="text-caption">Distrik Ransiki</p>
                    </div>
                </div>
                <p class="text-caption">Sistem Informasi dan Monitoring Kelembagaan Ekonomi: profil kelompok usaha, produk, dan kontak di Distrik Ransiki.</p>
                <p class="text-caption">Dikelola oleh Pemerintah Distrik Ransiki.</p>
            </div>

            @foreach ($footerLinks as $heading => $links)
                <div>
                    <h2 class="text-caption font-semibold uppercase tracking-wider text-paper">{{ $heading }}</h2>
                    <ul class="mt-3 text-ui">
                        @foreach ($links as [$label, $href])
                            <li><a href="{{ $href }}" class="inline-flex min-h-11 items-center rounded-control hover:text-paper hover:underline md:min-h-8">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div>
                <h2 class="text-caption font-semibold uppercase tracking-wider text-paper">Kantor Distrik Ransiki</h2>
                <dl class="mt-4 space-y-3 text-ui">
                    @foreach ([
                        ['map-pin', 'Alamat', $office['office_address'], null],
                        ['phone', 'Telepon', $office['office_phone'], $office['office_phone'] ? $tel($office['office_phone']) : null],
                        ['envelope', 'Email', $office['office_email'], $office['office_email'] ? 'mailto:'.$office['office_email'] : null],
                        ['clock', 'Jam layanan', $office['office_hours'], null],
                    ] as [$icon, $label, $value, $href])
                        @if ($value)
                            <div class="flex gap-3">
                                <dt><x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-0.5 size-5 text-forest-soft" aria-hidden="true" /><span class="sr-only">{{ $label }}</span></dt>
                                <dd class="min-w-0 whitespace-pre-line break-words">@if ($href)<a href="{{ $href }}" class="rounded-control text-paper hover:underline">{{ $value }}</a>@else{{ $value }}@endif</dd>
                            </div>
                        @endif
                    @endforeach
                    @unless ($office['office_address'] || $office['office_phone'] || $office['office_email'])
                        <div><dd>Kontak kantor sedang disiapkan.</dd></div>
                    @endunless
                </dl>
            </div>
        </div>
        <div class="border-t border-forest-line">
            <div class="mx-auto flex max-w-public flex-wrap justify-between gap-2 px-4 py-4 text-caption md:px-6">
                <p>&copy; {{ now()->year }} Distrik Ransiki</p>
                <a href="{{ route('public.contact') }}" class="rounded-control hover:text-paper">Hubungi kantor distrik</a>
            </div>
        </div>
    </footer>
</body>
</html>
