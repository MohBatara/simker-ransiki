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
    {{-- Efek gerak hanya bila JS aktif dan gerak tidak dikurangi (intro hero memakai kelas ini). --}}
    <script>if (! matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('motion');</script>
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

    {{--
        Tinggi <header> tetap (64/72px) agar isi halaman tidak bergeser; setelah digulir > 24px bilahnya menyusut 12px,
        dengan garis bawah dan bayangan halus (transisi 200ms). Panel cari/menu tampil di bawah bilah.
    --}}
    <header
        x-data="{ open: false, search: false }"
        x-on:keydown.escape="search = false; open = false"
        class="sticky top-0 z-40 h-16 md:h-18 print:hidden"
    >
        <div
            class="h-16 border-b border-transparent bg-paper transition-[height,box-shadow,border-color] duration-200 md:h-18"
            x-bind:class="{ 'h-13 md:h-15 border-line shadow-soft': $store.scroll.pastHeader, 'h-16 md:h-18 border-transparent': ! $store.scroll.pastHeader }"
        >
            <div class="mx-auto flex h-full max-w-public items-center justify-between gap-6 px-4 md:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-control leading-tight">
                    <x-logo />
                    <span>
                        <span class="block font-serif text-card-title font-semibold text-ink">SIM-KER</span>
                        <span class="flex items-center gap-1.5 text-caption text-ink-soft">
                            Distrik Ransiki
                            {{-- Penanda lingkungan non-produksi (latar wait-bg = emas 20% di atas putih). --}}
                            @unless (app()->isProduction())
                                <span class="rounded-full bg-wait-bg px-1.5 text-micro leading-4 font-semibold text-ink" aria-hidden="true">Mode uji</span>
                            @endunless
                        </span>
                    </span>
                </a>

                <div class="flex items-center gap-1">
                    <nav aria-label="Menu utama" class="hidden self-stretch lg:flex">
                        @foreach ($menu as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['active']) aria-current="page" @endif
                                @class([
                                    'flex h-full items-center border-b-2 px-3 transition-colors',
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
        {{-- < 1024px: identitas, dua kelompok tautan berdampingan, kontak selebar penuh; desktop empat kolom. --}}
        <div class="mx-auto grid max-w-public grid-cols-2 gap-x-6 gap-y-8 px-4 pt-12 pb-8 md:px-6 lg:grid-cols-[1.4fr_1fr_1fr_1.4fr] lg:gap-10 lg:py-12">
            <div class="col-span-2 space-y-4 lg:col-span-1">
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
                <div class="min-w-0">
                    <h2 class="text-caption font-semibold uppercase tracking-wider text-paper">{{ $heading }}</h2>
                    <ul class="mt-3 text-ui">
                        @foreach ($links as [$label, $href])
                            <li><a href="{{ $href }}" class="flex min-h-10 items-center rounded-control py-2 hover:text-paper hover:underline lg:inline-flex lg:min-h-8 lg:py-0">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="col-span-2 border-t border-paper/10 pt-8 lg:col-span-1 lg:border-0 lg:pt-0">
                <h2 class="text-caption font-semibold uppercase tracking-wider text-paper">Kantor Distrik Ransiki</h2>
                {{-- < 1024px: alamat dan jam selebar penuh, telepon dan email sebaris bila muat. --}}
                <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-3 text-ui lg:block lg:space-y-3">
                    @foreach ([
                        ['map-pin', 'Alamat', $office['office_address'], null],
                        ['phone', 'Telepon', $office['office_phone'], $office['office_phone'] ? $tel($office['office_phone']) : null],
                        ['envelope', 'Email', $office['office_email'], $office['office_email'] ? 'mailto:'.$office['office_email'] : null],
                        ['clock', 'Jam layanan', $office['office_hours'], null],
                    ] as [$icon, $label, $value, $href])
                        @if ($value)
                            <div @class(['flex min-w-0 gap-3', 'basis-full' => ! in_array($icon, ['phone', 'envelope'])])>
                                <dt><x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-px size-4.5 text-forest-soft lg:mt-0.5 lg:size-5" aria-hidden="true" /><span class="sr-only">{{ $label }}</span></dt>
                                <dd @class(['min-w-0 break-words lg:whitespace-pre-line', 'line-clamp-2' => $icon === 'map-pin'])>@if ($href)<a href="{{ $href }}" class="rounded-control text-paper hover:underline">{{ $value }}</a>@else{{ $value }}@endif</dd>
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
            <div class="mx-auto flex max-w-public flex-wrap justify-between gap-2 px-4 py-4 text-caption text-paper/60 md:px-6">
                <p>&copy; {{ now()->year }} Pemerintah Distrik Ransiki</p>
                <a href="{{ route('public.contact') }}" class="rounded-control hover:text-paper max-lg:hidden">Hubungi kantor distrik</a>
            </div>
        </div>
    </footer>
    {{-- Kembali ke atas: muncul setelah > 2 tinggi layar. --}}
    <button
        type="button"
        x-data
        x-on:click="window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"
        x-bind:class="$store.scroll.pastFold ? 'opacity-100 visible' : 'pointer-events-none invisible opacity-0'"
        class="invisible fixed right-4 bottom-6 z-30 flex size-11 items-center justify-center rounded-full bg-forest text-paper opacity-0 shadow-float transition-[opacity,visibility] duration-200 hover:bg-brand-hover md:bottom-14 md:right-3 print:hidden"
        aria-label="Kembali ke atas"
    >
        <x-heroicon-o-arrow-up class="size-5" aria-hidden="true" />
    </button>
</body>
</html>
