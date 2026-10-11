@use('App\Support\Format')

<x-layouts.public hero-header :description="$settings['home_intro'] ?? 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.'">
    {{-- Hero: foto sampul + overlay (gradasi hanya di sini), atau forest polos bila belum ada foto. --}}
    @php
        // "Panen kakao." + "Nama" → "Panen kakao · Foto: Nama" (titik akhir keterangan dibuang).
        $caption = collect([
            $settings['cover_credit'] ? rtrim((string) $settings['cover_caption'], '. ') : $settings['cover_caption'],
            $settings['cover_credit'] ? 'Foto: '.$settings['cover_credit'] : null,
        ])->filter()->join(' · ');
    @endphp

    {{-- -mt-16 + pt-16: hero mulai di bawah header beranda yang menempel di atasnya. --}}
    <section class="on-dark relative isolate -mt-16 overflow-hidden bg-forest pt-16 text-paper" data-hero>
        @if ($cover)
            <x-public.picture :image="$cover" :alt="$settings['cover_caption'] ?? ''" eager sizes="100vw" width="1600" height="686" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
            <div class="overlay-forest absolute inset-0 -z-10" aria-hidden="true"></div>
        @endif

        <div @class(['mx-auto flex min-h-[28rem] max-w-public flex-col justify-center px-4 py-14 md:min-h-[32rem] md:px-6', 'pb-20' => $cover && $caption])>
            {{-- Teks hanya di setengah kiri: di sana overlay paling tebal (kontras terburuk 5,5:1). --}}
            <div class="max-w-xl md:max-w-[50%]">
                <x-public.pill tone="dark">Direktori Kelembagaan Ekonomi</x-public.pill>
                <h1 class="mt-4 text-hero-sm font-semibold md:text-hero">Kelompok usaha dan kelembagaan ekonomi Distrik Ransiki</h1>
                @if ($settings['home_intro'])
                    <p class="mt-4 text-body text-paper">{{ $settings['home_intro'] }}</p>
                @endif

                <form method="GET" action="{{ route('public.directory') }}" role="search" class="mt-6 flex gap-2">
                    <label for="q" class="sr-only">Cari kelompok atau produk</label>
                    {{-- Placeholder pendek di HP; versi panjang dipasang di layar lebar. --}}
                    <x-input
                        name="q"
                        placeholder="Cari kelompok atau produk"
                        x-data="{ wide: window.matchMedia('(min-width: 48rem)') }"
                        x-init="const set = () => $el.placeholder = wide.matches ? 'Cari nama kelompok atau produk, mis. kakao' : 'Cari kelompok atau produk'; set(); wide.addEventListener('change', set)"
                        class="min-w-0 flex-1 md:h-12!"
                    />
                    <x-public.button type="submit" icon="magnifying-glass">Cari</x-public.button>
                </form>
                <a href="{{ route('public.products') }}" class="mt-4 inline-flex items-center gap-1 rounded-control text-ui text-forest-soft underline-offset-4 hover:text-paper hover:underline">
                    atau lihat semua produk <x-heroicon-o-arrow-right class="size-4" />
                </a>
            </div>
        </div>

        @if ($cover && $caption)
            {{-- Pojok kanan bawah: overlay di sisi kanan tipis, jadi teks diberi latar forest 90% (kontras 6,2:1). --}}
            <div class="pointer-events-none absolute inset-x-0 bottom-0">
                <div class="mx-auto flex max-w-public justify-end px-4 pb-4 md:px-6">
                    <p class="pointer-events-auto rounded-control bg-forest/90 px-2 py-1 text-caption text-forest-soft">{{ $caption }}</p>
                </div>
            </div>
        @endif
    </section>

    {{-- Pita kuning: angka asli, teks selalu ink (putih di atas kuning tidak lolos kontras). --}}
    <section class="bg-sun">
        <div class="mx-auto max-w-public px-4 py-6 md:px-6 md:py-8">
            @include('publik._stats', ['tone' => 'sun', 'class' => 'md:max-w-2xl'])
            @if ($stats['updated_at'])
                <p class="mt-3 text-caption text-ink">Data diperbarui {{ Format::date($stats['updated_at']) }}</p>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-public space-y-14 px-4 py-14 md:px-6">
        <section>
            @include('publik._section-heading', ['title' => 'Telusuri per bidang usaha'])
            @if ($sectors->isEmpty())
                <p class="mt-4 text-body text-ink-soft">Belum ada kelompok yang dipublikasikan.</p>
            @else
                <div class="mt-5 grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
                    @foreach ($sectors as $sector)
                        @include('publik._sector-card')
                    @endforeach
                </div>
            @endif
        </section>

        @if ($featured->isNotEmpty())
            <section>
                @include('publik._section-heading', ['title' => 'Produk unggulan', 'link' => ['href' => route('public.products'), 'label' => 'Lihat semua produk']])
                {{-- Berfoto: kartu bergambar. Belum ada yang berfoto: kartu ringkas tanpa area gambar. --}}
                <div class="mt-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($featured as $product)
                        @include('publik._product-card', ['compact' => $featuredCompact])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($posts->isNotEmpty())
            <section>
                @include('publik._section-heading', ['title' => 'Berita & kegiatan', 'link' => ['href' => route('public.posts'), 'label' => 'Semua berita']])
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        @include('publik._post-card')
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @if ($excerpt = Format::excerpt($settings['profile_body']))
        <section class="on-dark bg-forest text-paper">
            <div @class(['mx-auto grid max-w-public gap-8 px-4 py-14 md:items-center md:px-6', 'md:grid-cols-2' => $about])>
                @if ($about)
                    <x-public.picture :image="$about" alt="Distrik Ransiki" sizes="(min-width: 48rem) 34rem, 100vw" width="960" height="720" frame="block overflow-hidden rounded-card" class="aspect-[4/3] w-full object-cover" />
                @endif
                <div class="max-w-[42rem]">
                    <h2 class="text-page-title font-semibold">Tentang Distrik Ransiki</h2>
                    <p class="mt-4 text-body text-forest-soft">{{ $excerpt }}</p>
                    <a href="{{ route('public.profile') }}" class="mt-5 inline-flex items-center gap-2 rounded-control font-semibold text-sun hover:underline">
                        Baca profil lengkap <x-heroicon-o-arrow-right class="size-4" />
                    </a>
                </div>
            </div>
        </section>
    @endif
</x-layouts.public>
