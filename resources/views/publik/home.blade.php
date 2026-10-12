@use('App\Support\Format')
@use('App\Support\PublicIcons')

@php
    $text = fn (string $key) => $settings[$key] ?? App\Models\SiteSetting::DEFAULTS[$key];
    // Strip bidang usaha: 5 bidang dengan kelompok terbanyak.
    $topSectors = $sectors->sortByDesc('organizations_count')->take(5)->values();
@endphp

<x-layouts.public :description="$settings['home_intro'] ?? 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.'">
    {{-- Hero + strip + Tentang berbagi --strip-h (resources/css/app.css, .home-top). --}}
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
                <span class="block h-0.5 w-10 bg-sun" aria-hidden="true"></span>
                <h1 class="mt-5 font-serif text-hero-sm font-semibold md:text-hero">{{ $text('hero_title') }} <span class="text-sun">{{ $text('hero_title_accent') }}</span></h1>
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
                                    <span @class(['block h-2.5 rounded-full transition-all motion-reduce:transition-none', 'w-6 bg-sun' => $loop->first, 'w-2.5 bg-paper/70' => ! $loop->first]) x-bind:class="{ 'w-6 bg-sun': active === {{ $loop->index }}, 'w-2.5 bg-paper/70': active !== {{ $loop->index }} }" aria-hidden="true"></span>
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

    {{-- Tentang: angka hanya dari data publik (PublicDirectory::stats). Jarak atas = setengah strip + jarak normal. --}}
    <section class="mx-auto grid max-w-public gap-10 px-4 pt-[calc(var(--strip-h)/2+3.5rem)] pb-14 md:grid-cols-2 md:items-center md:px-6 md:pt-[calc(var(--strip-h)/2+5rem)] md:pb-20 lg:gap-14" aria-labelledby="tentang">
        @if ($about)
            <x-public.picture :image="$about" alt="Distrik Ransiki" sizes="(min-width: 64rem) 32rem, (min-width: 48rem) 50vw, 100vw" width="960" height="720" frame="block overflow-hidden rounded-public shadow-card" class="aspect-[4/3] w-full object-cover" />
        @endif
        <div @class(['max-w-[42rem]' => ! $about])>
            @include('publik._section-heading', ['eyebrow' => 'Tentang', 'title' => 'Distrik Ransiki', 'id' => 'tentang'])
            <p class="mt-4 text-body text-ink-soft">{{ Format::excerpt($settings['profile_body']) ?? 'Profil distrik sedang disiapkan.' }}</p>

            <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 max-md:sm:grid-cols-4 lg:grid-cols-4">
                @foreach ([['organizations', 'kelompok terdaftar'], ['villages', 'kampung'], ['featured', 'produk unggulan'], ['sectors', 'bidang usaha']] as [$key, $label])
                    <div class="flex flex-col-reverse justify-end border-l-2 border-sun pl-3">
                        <dt class="text-caption text-ink-soft">{{ $label }}</dt>
                        <dd class="font-serif text-display-sm font-semibold text-brand tabular-nums">{{ Format::number($stats[$key]) }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($stats['updated_at'])
                <p class="mt-4 text-caption text-ink-muted">Data diperbarui {{ Format::date($stats['updated_at']) }}</p>
            @endif

            <x-public.button variant="brand" :href="route('public.profile')" arrow class="mt-7">Selengkapnya</x-public.button>
        </div>
    </section>
    </div>

    @if ($types->isNotEmpty())
        <section class="border-y border-line bg-paper" aria-labelledby="jenis-kelembagaan">
            <div class="mx-auto max-w-public px-4 py-14 md:px-6 md:py-16">
                @include('publik._section-heading', ['eyebrow' => 'Kelembagaan', 'title' => 'Jenis kelembagaan', 'id' => 'jenis-kelembagaan', 'link' => ['href' => route('public.directory'), 'label' => 'Buka direktori']])
                <ul class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 lg:grid-cols-4">
                    @foreach ($types as $type)
                        <li>
                            <a href="{{ route('public.directory', ['jenis' => $type->id]) }}" class="group flex h-full flex-col items-start gap-3 rounded-public border border-line bg-paper p-4 shadow-card transition-colors hover:border-brand md:p-5">
                                <x-public.icon-badge variant="plain" :icon="PublicIcons::institutionType($type->name)" />
                                <span class="font-serif text-card-title font-semibold text-ink group-hover:text-brand">{{ $type->name }}</span>
                                <span class="mt-auto flex w-full items-center justify-between gap-2 text-ui text-ink-soft">
                                    <span class="tabular-nums">{{ Format::number($type->organizations_count) }} kelompok</span>
                                    <x-heroicon-o-arrow-right class="size-4 shrink-0 text-brand transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <x-public.button variant="outline-brand" :href="route('public.directory')" arrow class="mt-6 w-full sm:hidden">Buka direktori</x-public.button>
            </div>
        </section>
    @endif

    @if ($featured->isNotEmpty())
        <section class="mx-auto max-w-public px-4 py-14 md:px-6 md:py-16" aria-labelledby="produk-unggulan">
            @include('publik._section-heading', ['eyebrow' => 'Produk', 'title' => 'Produk unggulan', 'id' => 'produk-unggulan', 'link' => ['href' => route('public.products'), 'label' => 'Lihat semua produk']])
            <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
                @foreach ($featured as $product)
                    @include('publik._product-card')
                @endforeach
            </div>
            <x-public.button variant="outline-brand" :href="route('public.products')" arrow class="mt-6 w-full sm:hidden">Lihat semua produk</x-public.button>
        </section>
    @endif

    @if ($posts->isNotEmpty())
        <section @class(['border-y border-line bg-paper' => $featured->isNotEmpty()]) aria-labelledby="berita-kegiatan">
            <div class="mx-auto max-w-public px-4 py-14 md:px-6 md:py-16">
                @include('publik._section-heading', ['eyebrow' => 'Kabar distrik', 'title' => 'Berita & kegiatan', 'id' => 'berita-kegiatan', 'link' => ['href' => route('public.posts'), 'label' => 'Lihat semua berita']])
                {{-- HP: digeser horizontal; tablet 3 kolom; desktop satu baris. --}}
                <ul @class([
                    '-mx-4 mt-8 flex snap-x gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0 md:pb-0',
                    'lg:grid-cols-4' => $posts->count() === 4,
                    'lg:grid-cols-5' => $posts->count() === 5,
                ])>
                    @foreach ($posts as $post)
                        <li class="w-64 shrink-0 snap-start md:w-auto">
                            @include('publik._post-card', ['compact' => true])
                        </li>
                    @endforeach
                </ul>
                <x-public.button variant="outline-brand" :href="route('public.posts')" arrow class="mt-6 w-full sm:hidden">Lihat semua berita</x-public.button>
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-public px-4 py-14 md:px-6 md:py-16" aria-labelledby="ajakan">
        <div class="on-dark flex flex-col gap-6 rounded-public bg-forest px-5 py-8 text-paper md:flex-row md:items-center md:px-10">
            <h2 id="ajakan" class="font-serif text-display-sm font-semibold md:max-w-xs">Kelompok Anda <span class="text-sun">belum terdaftar?</span></h2>
            <p class="flex-1 text-body text-forest-soft md:border-l md:border-forest-line md:pl-8">Hubungi Kantor Distrik Ransiki agar kelompok usaha Anda didata dan profilnya tampil di direktori.</p>
            <x-public.button :href="route('public.contact')" arrow class="self-start md:self-center">Hubungi kami</x-public.button>
        </div>
    </section>
</x-layouts.public>
