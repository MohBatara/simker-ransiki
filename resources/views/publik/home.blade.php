@use('App\Support\Format')
@use('App\Support\PublicIcons')

@php
    $text = fn (string $key) => $settings[$key] ?? App\Models\SiteSetting::DEFAULTS[$key];
    // Strip bidang usaha: 5 bidang dengan kelompok terbanyak.
    $topSectors = $sectors->sortByDesc('organizations_count')->take(5)->values();
@endphp

<x-layouts.public :description="$settings['home_intro'] ?? 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.'">
    {{-- Jarak vertikal: py-section / mt-section-head (app.css). Hero + strip + Tentang berbagi --strip-h (.home-top). --}}
    <div class="home-page">
    <div class="home-top" @if ($topSectors->isNotEmpty()) data-strip @endif>
    <div class="relative">
    {{-- Hero: slide dari Admin (maks 3) + overlay; tanpa slide aktif, satu foto bawaan. --}}
    <section
        class="on-dark relative isolate overflow-hidden bg-forest text-paper"
        data-hero
        @if (count($slides) > 1)
            x-data="heroSlider({{ count($slides) }})"
            x-on:mouseenter="hovered = true"
            x-on:mouseleave="hovered = false"
            x-on:focusin="focused = true"
            x-on:focusout="blur($event)"
            aria-roledescription="carousel"
            aria-label="Foto Distrik Ransiki"
        @endif
    >
        @foreach ($slides as $slide)
            <div
                @class(['absolute inset-0 -z-20 transition-opacity duration-500 motion-reduce:transition-none', 'opacity-0' => ! $loop->first])
                @if (count($slides) > 1)
                    x-bind:class="{ 'opacity-0': active !== {{ $loop->index }} }"
                    x-bind:aria-hidden="active !== {{ $loop->index }}"
                    @unless ($loop->first) aria-hidden="true" @endunless
                @endif
            >
                {{-- Foto pertama dimuat segera dengan prioritas tinggi; sisanya lazy. --}}
                <x-public.picture :image="$slide['image']" :alt="$slide['alt']" :eager="$loop->first" sizes="100vw" width="1920" height="823" frame="block size-full" class="size-full object-cover {{ $slide['focus'] }}" />
            </div>
        @endforeach
        <div class="overlay-hero absolute inset-0 -z-10" aria-hidden="true"></div>

        {{-- Ruang bawah = setengah strip (yang menimpa foto) + ruang untuk titik slider/keterangan + jarak normal. --}}
        <div class="mx-auto flex min-h-[30rem] max-w-public flex-col justify-center px-4 pt-14 pb-[calc(var(--strip-h)/2+4.5rem)] md:min-h-[36rem] md:px-6 md:pb-[calc(var(--strip-h)/2+5rem)]">
            {{-- Teks hanya di setengah kiri: di sana overlay paling tebal (sun 5,0:1, putih 9,1:1 terburuk). --}}
            <div class="max-w-xl md:max-w-[50%]">
                <h1 class="font-serif text-hero-sm font-semibold md:text-hero">{{ $text('hero_title') }} <span class="text-sun">{{ $text('hero_title_accent') }}</span></h1>
                <p class="mt-4 text-body text-paper">{{ $text('home_intro') }}</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <x-public.button :href="route('public.directory')" arrow>Jelajahi direktori</x-public.button>
                    <x-public.button variant="outline" :href="route('public.products')" arrow>Produk unggulan</x-public.button>
                </div>
            </div>
        </div>

        @if (count($slides) > 1)
            <button type="button" x-on:click="prev()" class="absolute top-1/2 left-4 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full border border-paper/70 text-paper transition-colors hover:bg-paper/10 lg:flex xl:left-6">
                <span class="sr-only">Foto sebelumnya</span>
                <x-heroicon-o-chevron-left class="size-5" />
            </button>
            <button type="button" x-on:click="next()" class="absolute top-1/2 right-4 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full border border-paper/70 text-paper transition-colors hover:bg-paper/10 lg:flex xl:right-6">
                <span class="sr-only">Foto berikutnya</span>
                <x-heroicon-o-chevron-right class="size-5" />
            </button>
        @endif

        @if (count($slides) > 1 || collect($slides)->contains(fn ($slide) => $slide['caption']))
            <div class="pointer-events-none absolute inset-x-0 bottom-[calc(var(--strip-h)/2+0.75rem)]">
                <div class="mx-auto flex max-w-public flex-wrap items-center justify-end gap-3 px-4 md:px-6">
                    @if (count($slides) > 1)
                        <div class="pointer-events-auto mr-auto -ml-3 flex">
                            @foreach ($slides as $slide)
                                <button type="button" x-on:click="go({{ $loop->index }})" x-bind:aria-current="active === {{ $loop->index }}" class="group/dot flex size-11 items-center justify-center rounded-full">
                                    <span class="sr-only">Foto {{ $loop->iteration }} dari {{ $loop->count }}</span>
                                    <span @class(['block h-2.5 rounded-full transition-all motion-reduce:transition-none', 'w-6 bg-paper' => $loop->first, 'w-2.5 bg-paper/60' => ! $loop->first]) x-bind:class="{ 'w-6 bg-paper': active === {{ $loop->index }}, 'w-2.5 bg-paper/60': active !== {{ $loop->index }} }" aria-hidden="true"></span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @foreach ($slides as $slide)
                        @if ($slide['caption'])
                            {{-- Overlay di sisi kanan tipis, jadi teks diberi latar forest 90% (kontras 6,2:1). --}}
                            <p
                                class="pointer-events-auto rounded-control bg-forest/90 px-2 py-1 text-caption text-forest-soft max-md:hidden"
                                @if (count($slides) > 1) x-show="active === {{ $loop->index }}" @unless ($loop->first) x-cloak @endunless @endif
                            >{{ $slide['caption'] }}</p>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{--
        Strip bidang usaha: di luar <section> hero (yang overflow-hidden), absolute di batas bawahnya lalu
        translate-y-1/2, sehingga garis bawah foto tepat di tengah tinggi strip. Di HP digeser horizontal.
    --}}
    @if ($topSectors->isNotEmpty())
        <div class="absolute inset-x-0 bottom-0 z-10 translate-y-1/2">
            <div class="mx-auto max-w-public px-4 md:px-6">
            <nav aria-label="Bidang usaha" class="h-(--strip-h) overflow-hidden rounded-public border border-line bg-paper shadow-strip">
                <ul class="flex h-full snap-x divide-x divide-line overflow-x-auto lg:overflow-visible">
                    @foreach ($topSectors as $sector)
                        <li class="w-44 shrink-0 snap-start lg:w-auto lg:min-w-0 lg:flex-1">
                            <a href="{{ route('public.directory', ['bidang' => $sector->id]) }}" class="group flex h-full items-center gap-3 px-4 transition-colors hover:bg-subtle md:px-5">
                                <x-public.icon :name="PublicIcons::sector($sector->name)" class="size-8 shrink-0 text-brand" />
                                <span class="min-w-0">
                                    <span class="line-clamp-2 font-semibold text-ink group-hover:text-brand">{{ $sector->name }}</span>
                                    <span class="block text-caption text-ink-soft tabular-nums">{{ Format::number($sector->organizations_count) }} kelompok</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
            </div>
        </div>
    @endif
    </div>

    {{--
        Mengenal Distrik Ransiki: kepala dua kolom, tiga kartu foto (sudut kanan bawah "tergigit" berisi tombol panah),
        lalu baris angka publik. < 1024px kartu digeser (scroll-snap) dengan titik penanda. Jarak atas = setengah strip.
    --}}
    @php
        $statItems = [['organizations', 'Kelompok'], ['villages', 'Kampung'], ['featured', 'Produk unggulan'], ['sectors', 'Bidang usaha']];
    @endphp
    @php
        // Desktop: kata pertama judul di baris sendiri ("Mengenal" / "Distrik Ransiki"); HP mengalir natural.
        $aboutTitle = $text('about_title');
    @endphp
    {{-- Pengecualian skala: jarak atas = setengah strip + 56px; bawah = pb-section. --}}
    <section class="mx-auto max-w-public px-4 pt-[calc(var(--strip-h)/2+3.5rem)] pb-section md:px-6" aria-labelledby="tentang">
        <div class="grid gap-3 md:grid-cols-12 md:items-start md:gap-x-6">
            <h2 id="tentang" class="font-serif text-display-sm font-semibold text-balance text-ink md:col-span-5 md:text-display lg:text-hero-sm">
                @if (str_contains($aboutTitle, ' '))
                    {{ Str::before($aboutTitle, ' ') }}<br class="hidden lg:block"> {{ Str::after($aboutTitle, ' ') }}
                @else
                    {{ $aboutTitle }}
                @endif
            </h2>
            <div class="md:col-span-6 md:col-start-7">
                <p class="max-w-[26rem] text-body leading-[1.6] text-ink-soft">{{ Format::excerpt($settings['profile_body']) ?? 'Profil distrik sedang disiapkan.' }}</p>
                <x-public.pill-link :href="route('public.profile')" class="mt-6">Baca profil lengkap</x-public.pill-link>
            </div>
        </div>

        <div x-data="scrollDots" class="mt-section-head">
            {{-- < 1024px: deretan geser selebar layar (−mx-4), kartu 85vw (HP) atau setengah (tablet); desktop 3 kolom. --}}
            <ul x-ref="track" x-on:scroll.passive="update()" class="scrollbar-none relative -mx-4 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-1 md:-mx-6 md:scroll-px-6 md:px-6 lg:mx-0 lg:grid lg:grid-cols-3 lg:gap-6 lg:overflow-visible lg:px-0 lg:pb-0">
                @foreach ($aboutCards as $card)
                    <li class="relative w-[85vw] shrink-0 snap-start md:w-[calc(50%-0.5rem)] lg:w-auto">
                        <a href="{{ $card['url'] }}" class="group relative block rounded-[20px]">
                            <div class="card-bite relative isolate flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-[20px] bg-forest">
                                <x-public.picture :image="$card['photo']" alt="" sizes="(min-width: 64rem) 22rem, (min-width: 48rem) 50vw, 85vw" width="960" height="1200" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04] group-focus-visible:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
                                <div class="relative px-5 pt-5 pr-20 pb-6">
                                    <div class="overlay-photo-fade absolute -inset-x-0 -top-20 bottom-0 -z-10" aria-hidden="true"></div>
                                    <h3 class="font-serif text-page-title font-semibold text-paper">{{ $card['title'] }}</h3>
                                    <p class="mt-2 text-ui text-paper/85">{{ $card['text'] }}</p>
                                </div>
                            </div>
                            {{-- Tombol di luar elemen ber-mask, tepat di pusat potongan. --}}
                            <span class="absolute right-0 bottom-0 flex size-13 items-center justify-center rounded-full bg-sun text-ink" aria-hidden="true">
                                <x-heroicon-o-arrow-right class="size-5 transition-transform duration-150 group-hover:translate-x-0.75 group-focus-visible:translate-x-0.75 motion-reduce:transition-none" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex justify-center lg:hidden">
                @foreach ($aboutCards as $card)
                    <button type="button" x-on:click="go({{ $loop->index }})" x-bind:aria-current="active === {{ $loop->index }}" class="flex size-11 items-center justify-center rounded-full">
                        <span class="sr-only">Kartu {{ $loop->iteration }} dari {{ $loop->count }}</span>
                        <span @class(['block h-2 rounded-full transition-all motion-reduce:transition-none', 'w-6 bg-brand' => $loop->first, 'w-2 bg-line-input' => ! $loop->first]) x-bind:class="{ 'w-6 bg-brand': active === {{ $loop->index }}, 'w-2 bg-line-input': active !== {{ $loop->index }} }" aria-hidden="true"></span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Angka hanya dari data publik (PublicDirectory::stats). HP 2×2; mulai 768px satu baris dipisah titik tengah. --}}
        <div class="mt-section-head flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3 border-t border-line pt-6">
            <dl class="grid w-full grid-cols-2 gap-x-6 gap-y-3 md:flex md:w-auto md:flex-wrap md:items-baseline md:gap-x-4">
                @foreach ($statItems as [$key, $label])
                    @unless ($loop->first)
                        <span class="text-ink-muted max-md:hidden" aria-hidden="true">·</span>
                    @endunless
                    <div class="flex items-baseline gap-2">
                        <dd class="order-first font-serif text-page-title font-semibold text-brand tabular-nums">{{ Format::number($stats[$key]) }}</dd>
                        <dt class="text-ui text-ink-soft">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
            @if ($stats['updated_at'])
                <p class="text-caption text-ink-muted">Data per {{ Format::date($stats['updated_at']) }}</p>
            @endif
        </div>
    </section>
    </div>

    {{--
        Jenis kelembagaan: satu foto besar + daftar. Desktop: foto 5 kolom (sticky) berganti mengikuti baris aktif
        (hover/fokus, crossfade 300ms), daftar 7 kolom. < 1024px: foto 16:9 jenis terbanyak di atas, lalu daftar.
        Kontras: label putih di pita forest 85% di atas foto putih 9,2:1; angka brand dan teks ink-soft di atas cream 50%.
    --}}
    @if ($types->isNotEmpty())
        <section class="border-y border-line bg-paper" aria-labelledby="jenis-kelembagaan" x-data="{ active: 0 }">
            <div class="mx-auto max-w-public px-4 py-section md:px-6">
                <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                    <div>
                        <h2 id="jenis-kelembagaan" class="font-serif text-display-sm font-semibold text-ink">Jenis kelembagaan</h2>
                        <p class="mt-3 max-w-[34rem] text-body text-ink-soft">Kelompok usaha di Distrik Ransiki menurut bentuk kelembagaannya. Pilih salah satu untuk melihat daftar kelompoknya.</p>
                    </div>
                    <a href="{{ route('public.directory') }}" class="group inline-flex items-center gap-2 rounded-control font-semibold text-brand underline-offset-4 hover:underline focus-visible:underline max-lg:hidden">
                        Lihat semua kelompok
                        <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                    </a>
                </div>

                <div class="mt-section-head grid gap-8 lg:grid-cols-12 lg:items-start lg:gap-12">
                    {{-- Panel foto (desktop saja): dekoratif (nama dan jumlah ada di daftar), jadi alt kosong dan aria-hidden. --}}
                    <div class="relative overflow-hidden rounded-tile bg-subtle max-lg:hidden lg:sticky lg:top-24 lg:col-span-5 lg:aspect-[4/5]" aria-hidden="true">
                        @foreach ($types as $type)
                            <div
                                @class([
                                    'absolute inset-0 transition-opacity duration-300 motion-reduce:transition-none',
                                    'lg:opacity-0' => ! $loop->first,
                                ])
                                x-bind:class="{ 'lg:opacity-0': active !== {{ $loop->index }} }"
                            >
                                {{-- Panel tersembunyi di < 1024px: sizes 1px agar foto pertama (tidak lazy) cukup versi terkecil. --}}
                                <x-public.picture :image="$type->photo" alt="" :lazy="! $loop->first" sizes="(min-width: 64rem) 26rem, 1px" width="960" height="1200" frame="block size-full" class="size-full object-cover" />
                                <p class="absolute bottom-4 left-4 max-w-[calc(100%-2rem)] rounded-control bg-forest/85 px-3 py-2 text-paper">
                                    <span class="block font-serif text-card-title font-semibold">{{ $type->name }}</span>
                                    <span class="block text-caption tabular-nums">{{ Format::number($type->organizations_count) }} kelompok</span>
                                </p>
                            </div>
                        @endforeach
                    </div>

                    {{-- < 1024px: [foto kecil] [nama, deskripsi, jumlah] [panah]; tablet dua kolom. Desktop: [angka] [teks] [panah]. --}}
                    <ul class="border-b border-line md:grid md:grid-cols-2 md:gap-x-8 lg:col-span-7 lg:block">
                        @foreach ($types as $type)
                            <li class="border-t border-line">
                                <a
                                    href="{{ route('public.directory', ['jenis' => $type->id]) }}"
                                    x-on:mouseenter="active = {{ $loop->index }}"
                                    x-on:focus="active = {{ $loop->index }}"
                                    @class([
                                        'group grid min-h-11 grid-cols-[4.5rem_minmax(0,1fr)_auto] items-center gap-4 py-4 transition-colors duration-150 motion-reduce:transition-none lg:grid-cols-[4rem_minmax(0,1fr)_auto] lg:gap-6 lg:px-4 lg:py-6',
                                        'lg:bg-cream/60' => $loop->first,
                                    ])
                                    x-bind:class="{ 'lg:bg-cream/60': active === {{ $loop->index }} }"
                                >
                                    <span class="font-serif text-numeral font-semibold text-brand tabular-nums max-lg:hidden" aria-hidden="true">{{ Format::number($type->organizations_count) }}</span>
                                    <x-public.picture :image="$type->photo" alt="" sizes="72px" width="72" height="72" frame="block size-18 overflow-hidden rounded-photo bg-subtle lg:hidden" class="size-full object-cover" />
                                    <span class="min-w-0">
                                        <span class="block font-serif text-title-sm font-semibold text-ink lg:text-page-title">{{ $type->name }}</span>
                                        @if ($type->description ?? null)
                                            <span class="mt-1 block text-ui text-ink-soft">{{ $type->description }}</span>
                                        @endif
                                        <span class="mt-1 block text-caption text-brand lg:sr-only"><span class="font-semibold tabular-nums">{{ Format::number($type->organizations_count) }}</span> kelompok</span>
                                    </span>
                                    <span
                                        @class([
                                            'flex size-9 shrink-0 items-center justify-center rounded-full border transition-colors duration-150 motion-reduce:transition-none lg:size-11',
                                            'border-line-strong text-brand' => ! $loop->first,
                                            'border-line-strong text-brand lg:border-brand lg:bg-brand lg:text-paper' => $loop->first,
                                        ])
                                        x-bind:class="{ 'lg:border-brand lg:bg-brand lg:text-paper': active === {{ $loop->index }} }"
                                        aria-hidden="true"
                                    >
                                        <x-heroicon-o-arrow-right
                                            @class(['size-4 transition-transform duration-150 motion-reduce:transition-none lg:size-5', 'lg:translate-x-1' => $loop->first])
                                            x-bind:class="{ 'lg:translate-x-1': active === {{ $loop->index }} }"
                                        />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <x-public.button variant="outline-brand" :href="route('public.directory')" arrow class="mt-6 w-full lg:hidden">Lihat semua kelompok</x-public.button>
            </div>
        </section>
    @endif

    @if ($featured->isNotEmpty())
        {{-- Produk unggulan: HP digeser (scroll-snap, kartu 80% layar, titik penanda), tablet 2 kolom, desktop 4 kolom. --}}
        <section class="mx-auto max-w-public px-4 py-section md:px-6" aria-labelledby="produk-unggulan">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="produk-unggulan" class="font-serif text-display-sm font-semibold text-ink">Produk unggulan</h2>
                <x-public.pill-link :href="route('public.products')" class="max-md:hidden">Lihat semua produk</x-public.pill-link>
            </div>
            <div x-data="scrollDots" class="mt-section-head">
                <ul x-ref="track" x-on:scroll.passive="update()" class="scrollbar-none relative -mx-4 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-1 md:mx-0 md:grid md:grid-cols-2 md:gap-5 md:overflow-visible md:px-0 md:pb-0 lg:grid-cols-4">
                    @foreach ($featured as $product)
                        <li class="w-[80vw] shrink-0 snap-start md:w-auto">
                            @include('publik._product-card')
                        </li>
                    @endforeach
                </ul>
                @if ($featured->count() > 1)
                    <div class="mt-4 flex justify-center md:hidden">
                        @foreach ($featured as $product)
                            <button type="button" x-on:click="go({{ $loop->index }})" x-bind:aria-current="active === {{ $loop->index }}" class="flex size-11 items-center justify-center rounded-full">
                                <span class="sr-only">Produk {{ $loop->iteration }} dari {{ $loop->count }}</span>
                                <span @class(['block h-2 rounded-full transition-all motion-reduce:transition-none', 'w-6 bg-brand' => $loop->first, 'w-2 bg-line-input' => ! $loop->first]) x-bind:class="{ 'w-6 bg-brand': active === {{ $loop->index }}, 'w-2 bg-line-input': active !== {{ $loop->index }} }" aria-hidden="true"></span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <x-public.pill-link :href="route('public.products')" full class="mt-6 md:hidden">Lihat semua produk</x-public.pill-link>
        </section>
    @endif

    {{--
        Berita dan kegiatan (gaya portal): berita utama 7 kolom + daftar 4 berikutnya 5 kolom (desktop);
        < 1024px berita utama lalu 3 item daftar. Disembunyikan bila belum ada post terbit; satu post = lebar penuh.
    --}}
    @if ($newsLead)
        <section class="border-y border-line bg-paper" aria-labelledby="berita-kegiatan">
            <div class="mx-auto max-w-public px-4 py-section md:px-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="berita-kegiatan" class="font-serif text-display-sm font-semibold text-ink">Berita dan kegiatan</h2>
                    <x-public.pill-link :href="route('public.posts')" class="max-lg:hidden">Lihat semua berita</x-public.pill-link>
                </div>

                <div class="mt-section-head grid gap-y-10 lg:grid-cols-12 lg:gap-x-12">
                    @php
                        $lead = $newsLead;
                        $leadCover = $lead->coverSources();
                    @endphp
                    <article @class(['group relative min-w-0 has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand', 'lg:col-span-7' => $newsList->isNotEmpty(), 'lg:col-span-12' => $newsList->isEmpty()])>
                        <div class="aspect-[16/9] overflow-hidden rounded-tile bg-cream lg:aspect-[16/10]">
                            @if ($leadCover)
                                <x-public.picture :image="$leadCover" alt="" sizes="(min-width: 64rem) 37rem, 100vw" width="960" height="600" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
                            @else
                                <span class="flex size-full items-center justify-center text-clay" aria-hidden="true">
                                    <x-dynamic-component :component="'heroicon-o-'.$lead->category->icon()" class="size-20" />
                                </span>
                            @endif
                        </div>
                        <p class="mt-5 flex flex-wrap items-center gap-x-2 text-caption text-ink-muted">
                            <span class="font-semibold tracking-wider text-clay uppercase">{{ $lead->category->label() }}</span>
                            <span aria-hidden="true">·</span>
                            <time datetime="{{ $lead->published_at->toAtomString() }}">{{ Format::date($lead->published_at) }}</time>
                            <span aria-hidden="true">·</span>
                            <span>{{ $lead->readingMinutes() }} menit baca</span>
                        </p>
                        <h3 class="mt-3 font-serif text-headline font-semibold text-ink lg:text-display"><a href="{{ route('public.post', $lead->slug) }}" class="after:absolute after:inset-0 hover:text-brand focus-visible:outline-none">{{ $lead->title }}</a></h3>
                        @if ($leadSummary = $lead->summary ?: Format::excerpt($lead->body, 200))
                            <p class="mt-3 line-clamp-3 max-w-[38rem] text-body leading-[1.6] text-ink-soft">{{ $leadSummary }}</p>
                        @endif
                        <span class="mt-4 inline-flex items-center gap-2 font-semibold text-brand underline-offset-4 group-hover:underline" aria-hidden="true">
                            Baca selengkapnya
                            <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" />
                        </span>
                    </article>

                    @if ($newsList->isNotEmpty())
                        <ul class="min-w-0 divide-y divide-line lg:col-span-5">
                            @foreach ($newsList as $post)
                                @php $cover = $post->coverSources(); @endphp
                                {{-- < 1024px hanya 3 item. --}}
                                <li @class(['py-4 first:pt-0 last:pb-0', 'max-lg:hidden' => $loop->index >= 3])>
                                    <article class="group relative flex gap-4 has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand">
                                        <div class="h-20 w-26 shrink-0 overflow-hidden rounded-card bg-cream">
                                            @if ($cover)
                                                <x-public.picture :image="$cover" alt="" sizes="104px" width="104" height="80" frame="block size-full" class="size-full object-cover" />
                                            @else
                                                <span class="flex size-full items-center justify-center text-clay" aria-hidden="true">
                                                    <x-dynamic-component :component="'heroicon-o-'.$post->category->icon()" class="size-7" />
                                                </span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="flex flex-wrap items-center gap-x-2 text-caption text-ink-muted">
                                                <span class="font-semibold tracking-wider text-clay uppercase">{{ $post->category->label() }}</span>
                                                <span aria-hidden="true">·</span>
                                                <time datetime="{{ $post->published_at->toAtomString() }}">{{ Format::dateShort($post->published_at) }}</time>
                                            </p>
                                            <h3 class="mt-1 line-clamp-3 font-serif text-card-title font-semibold"><a href="{{ route('public.post', $post->slug) }}" class="text-ink underline-offset-4 after:absolute after:inset-0 group-hover:text-brand group-hover:underline focus-visible:outline-none">{{ $post->title }}</a></h3>
                                        </div>
                                    </article>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <x-public.pill-link :href="route('public.posts')" full class="mt-8 lg:hidden">Lihat semua berita</x-public.pill-link>
            </div>
        </section>
    @endif

    {{--
        Ajakan + alur pendaftaran (teks langkah: Konten Website, kunci how_*). Bagian terang agar tidak ada dua blok
        gelap berturut-turut sebelum footer. Kontras di cream: clay 7,1, ink 14,0, ink-soft 7,0.
    --}}
    {{-- Section kanvas + panel cream; panel memakai padding section yang sama agar tingginya konsisten. --}}
    <section class="mx-auto max-w-public px-4 py-section md:px-6" aria-labelledby="ajakan">
        <div class="grid gap-8 rounded-panel bg-cream px-6 py-section md:px-10 lg:grid-cols-12 lg:gap-16 lg:px-14">
            <div class="lg:col-span-5">
                <h2 id="ajakan" class="font-serif text-display-sm font-semibold text-ink md:text-display">Kelompok Anda belum terdaftar?</h2>
                <p class="mt-3 max-w-[28rem] text-body leading-7 text-ink-soft">Pendaftaran dilakukan melalui Kantor Distrik Ransiki. Begini alurnya.</p>
                <x-public.pill-link :href="route('public.contact')" class="mt-8 max-lg:hidden">Hubungi Kantor Distrik</x-public.pill-link>
            </div>

            <ol class="lg:col-span-7">
                @foreach (range(1, 4) as $n)
                    <li class="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-4 border-t border-line py-5 first:border-t-0 first:pt-0 last:pb-0">
                        <span class="font-serif text-display-sm font-semibold text-clay tabular-nums" aria-hidden="true">{{ sprintf('%02d', $n) }}</span>
                        <div>
                            <h3 class="text-card-title font-semibold text-ink"><span class="sr-only">Langkah {{ $n }}: </span>{{ $text("how_{$n}_title") }}</h3>
                            <p class="mt-1 text-ui text-ink-soft">{{ $text("how_{$n}_text") }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <x-public.pill-link :href="route('public.contact')" full class="lg:hidden">Hubungi Kantor Distrik</x-public.pill-link>
        </div>
    </section>
    </div>
</x-layouts.public>
