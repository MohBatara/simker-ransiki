@use('App\Support\Format')
@use('App\Support\PublicIcons')

@php
    $text = fn (string $key) => $settings[$key] ?? App\Models\SiteSetting::DEFAULTS[$key];
    // Strip bidang usaha: 5 bidang dengan kelompok terbanyak.
    $topSectors = $sectors->sortByDesc('organizations_count')->take(5)->values();
@endphp

<x-layouts.public :description="$settings['home_intro'] ?? 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.'">
    {{-- .home-page: satu jarak bagian (--section-y). Hero + strip + Tentang berbagi --strip-h (.home-top). --}}
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

    {{-- Tentang: angka hanya dari data publik (PublicDirectory::stats). Jarak atas = setengah strip + jarak bagian. --}}
    @php
        // Grid angka: 2×2 sampai < 1024px, satu baris 4 kolom mulai lg; garis pemisah tipis warna line.
        $statCells = [
            ['organizations', 'Kelompok', ''],
            ['villages', 'Kampung', 'border-l pl-4 md:pl-5'],
            ['featured', 'Produk unggulan', 'border-t lg:border-t-0 lg:border-l lg:pl-5'],
            ['sectors', 'Bidang usaha', 'border-t border-l pl-4 md:pl-5 lg:border-t-0'],
        ];
    @endphp
    <section class="mx-auto max-w-public px-4 pt-[calc(var(--strip-h)/2+var(--section-y))] pb-(--section-y) md:px-6" aria-labelledby="tentang">
        <div class="grid gap-8 md:grid-cols-12 md:items-center md:gap-10 lg:gap-16">
            @if ($about)
                <x-public.picture :image="$about" alt="Distrik Ransiki" sizes="(min-width: 64rem) 27rem, (min-width: 48rem) 40vw, 100vw" width="960" height="1200" frame="block overflow-hidden rounded-photo bg-subtle shadow-soft md:col-span-5" class="aspect-[4/3] w-full object-cover md:aspect-[4/5]" />
            @endif
            <div @class(['md:col-span-7' => $about, 'md:col-span-12 max-w-[42rem]' => ! $about])>
                <h2 id="tentang" class="font-serif text-display-sm font-semibold text-ink md:text-display">{{ $text('about_title') }}</h2>
                <p class="mt-5 max-w-[30rem] text-body leading-7 text-ink-soft">{{ Format::excerpt($settings['profile_body']) ?? 'Profil distrik sedang disiapkan.' }}</p>

                <dl class="mt-10 grid grid-cols-2 border-t border-line lg:grid-cols-4">
                    @foreach ($statCells as [$key, $label, $cell])
                        <div class="flex min-w-0 flex-col-reverse justify-end border-line py-5 pr-4 {{ $cell }}">
                            <dt class="mt-1 whitespace-nowrap text-caption text-ink-muted">{{ $label }}</dt>
                            <dd class="font-serif text-display-sm font-semibold text-brand tabular-nums md:text-display">{{ Format::number($stats[$key]) }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($stats['updated_at'])
                    <p class="mt-3 text-caption text-ink-muted">Data per {{ Format::date($stats['updated_at']) }}</p>
                @endif

                <a href="{{ route('public.profile') }}" class="group mt-8 inline-flex min-h-11 items-center gap-2 rounded-control font-semibold text-brand underline-offset-4 hover:underline focus-visible:underline md:min-h-0">
                    Baca profil lengkap
                    <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                </a>
            </div>
        </div>
    </section>
    </div>

    {{-- Cara kerja SIM-KER: panel forest (teks dari Konten Website, bawaan SiteSetting::DEFAULTS). --}}
    @php
        $steps = [
            [1, 'user-plus', ''],
            [2, 'pencil-square', 'border-t md:border-t-0 md:border-l'],
            [3, 'calendar-days', 'border-t md:pl-0 lg:border-t-0 lg:border-l lg:pl-6'],
            [4, 'check-badge', 'border-t md:border-l lg:border-t-0'],
        ];
    @endphp
    <section class="mx-auto max-w-public px-4 pb-(--section-y) md:px-6" aria-labelledby="cara-kerja">
        <div class="on-dark relative isolate overflow-hidden rounded-panel bg-forest px-6 py-10 text-paper md:px-10 md:py-14 lg:px-14">
            {{-- Motif daun sederhana, 6%, hanya hiasan. --}}
            <svg viewBox="0 0 24 24" fill="currentColor" class="pointer-events-none absolute -top-16 -right-16 -z-10 size-80 rotate-12 text-paper opacity-[0.06]" aria-hidden="true">
                <path d="M21 3C11 3 4 8 4 16c0 1.6.4 3.1 1 4.4C6.5 13 11 8.5 17 6.5 12 9.5 8.3 13.6 6.6 20.9 7.9 21.6 9.4 22 11 22c7 0 10-7 10-19Z" />
            </svg>
            <h2 id="cara-kerja" class="font-serif text-display-sm font-semibold md:text-display">{{ $text('how_title') }}</h2>
            <ol class="mt-10 grid md:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as [$n, $icon, $cell])
                    <li @class(['border-paper/15 py-6 first:pt-0 last:pb-0 md:px-6 md:first:pl-0 md:[&:nth-child(-n+2)]:pt-0 md:[&:nth-child(n+3)]:pb-0 lg:py-0 lg:last:pr-0', $cell])>
                        <span class="flex size-12 items-center justify-center rounded-full border border-paper/40" aria-hidden="true">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6 text-paper" />
                        </span>
                        <h3 class="mt-5 font-serif text-card-title font-semibold"><span class="sr-only">Langkah {{ $n }}: </span>{{ $text("how_{$n}_title") }}</h3>
                        <p class="mt-2 text-ui text-paper/80">{{ $text("how_{$n}_text") }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{--
        Jenis kelembagaan (bento): kartu utama 2×2 untuk jenis terbanyak, kartu kecil bergiliran forest, clay, cream.
        Grid selalu penuh untuk 2 sampai 9 jenis; kartu kecil terakhir melebar mengisi sisa baris (App\Support\BentoGrid).
        Kontras: putih di forest 14,6 dan clay 8,4 (putih 85%: 11,0 dan 6,6); ink di cream 14,0 (ink-soft 7,0);
        kartu utama putih di atas foto + lapisan forest ≥ 70% di area teks (terburuk 5,6); kartu kecil berfoto: teks
        berakhir sebelum foto lebih dari 10% menutupi warna kartu (diukur di PublicContrastTest).
    --}}
    @if ($types->isNotEmpty())
        @php
            $tones = [
                'forest' => ['surface' => 'bg-forest text-paper', 'soft' => 'text-paper/85', 'ring' => 'ring-forest', 'more' => 'bg-paper/15 text-paper'],
                'clay' => ['surface' => 'bg-clay text-paper', 'soft' => 'text-paper/85', 'ring' => 'ring-clay', 'more' => 'bg-paper/15 text-paper'],
                'cream' => ['surface' => 'bg-cream text-ink', 'soft' => 'text-ink-soft', 'ring' => 'ring-cream', 'more' => 'bg-paper text-ink'],
            ];
            $small = $types->count() - 1;
        @endphp
        <section class="border-y border-line bg-paper" aria-labelledby="jenis-kelembagaan">
            <div class="mx-auto max-w-public px-4 py-(--section-y) md:px-6">
                @include('publik._section-heading', ['title' => 'Jenis kelembagaan', 'id' => 'jenis-kelembagaan', 'link' => ['href' => route('public.directory'), 'label' => 'Buka direktori']])
                <p class="mt-3 max-w-[34rem] text-body text-ink-soft">Kelompok usaha di Distrik Ransiki menurut bentuk kelembagaannya. Pilih salah satu untuk melihat daftar kelompoknya.</p>

                <ul x-data="reveal(60)" class="mt-8 grid grid-cols-1 gap-4 md:auto-rows-[minmax(15rem,auto)] md:grid-cols-2 md:gap-5 lg:grid-cols-4">
                    @foreach ($types as $type)
                        @php
                            $isFeatured = $loop->first;
                            $tone = $isFeatured ? $tones['forest'] : $tones[array_keys($tones)[($loop->index - 1) % 3]];
                            $photo = $type->photo;
                        @endphp
                        <li @class([
                            'min-w-0',
                            'md:col-span-2 md:row-span-2' => $isFeatured,
                            App\Support\BentoGrid::smallClasses($loop->index - 1, $small) => ! $isFeatured,
                        ])>
                            <a
                                href="{{ route('public.directory', ['jenis' => $type->id]) }}"
                                @class([
                                    'group relative isolate flex h-full flex-col overflow-hidden rounded-tile shadow-soft transition-transform duration-150 hover:-translate-y-0.5 focus-visible:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0 motion-reduce:focus-visible:translate-y-0',
                                    'min-h-[26rem] p-6 md:min-h-[32rem] md:p-8' => $isFeatured,
                                    'min-h-60 p-6' => ! $isFeatured,
                                    $tone['surface'],
                                ])
                            >
                                @if ($photo && $isFeatured)
                                    <x-public.picture :image="$photo" alt="" sizes="(min-width: 64rem) 34rem, 100vw" width="960" height="720" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
                                @elseif ($photo)
                                    {{-- 45% kanan, memudar mulus ke warna kartu (mask), opasitas 85%; teks tetap di atas warna polos. --}}
                                    <div class="mask-fade-left absolute inset-y-0 right-0 -z-10 w-[45%] opacity-85" aria-hidden="true">
                                        <x-public.picture :image="$photo" alt="" sizes="(min-width: 64rem) 7rem, (min-width: 48rem) 22vw, 45vw" width="640" height="480" frame="block size-full" class="size-full object-cover" />
                                    </div>
                                @else
                                    {{-- Ikon jenis besar sebagai tekstur, sedikit terpotong tepi kartu; tidak dipakai bila kartu berfoto. --}}
                                    <x-public.icon
                                        :name="PublicIcons::institutionType($type->name)"
                                        @class([
                                            'pointer-events-none absolute -z-10 opacity-[0.08] transition duration-150 group-hover:scale-105 group-hover:opacity-[0.12] group-focus-visible:scale-105 group-focus-visible:opacity-[0.12] motion-reduce:transition-none motion-reduce:group-hover:scale-100',
                                            '-right-8 -bottom-8 size-56' => $isFeatured,
                                            '-right-6 -bottom-6 size-36' => ! $isFeatured,
                                        ])
                                    />
                                @endif

                                {{-- Kartu utama: isi di bawah; lapisan gelap menempel pada blok isi (≥ 70% di bawah teks) lalu memudar ke atas. --}}
                                <div @class(['relative flex flex-col', 'mt-auto' => $isFeatured, 'h-full' => ! $isFeatured, 'pr-[42%]' => $photo && ! $isFeatured])>
                                    @if ($photo && $isFeatured)
                                        <div class="overlay-bento-fade absolute -inset-x-6 -bottom-6 -top-28 -z-10 md:-inset-x-8 md:-bottom-8 md:-top-36 lg:-top-24" aria-hidden="true"></div>
                                    @endif
                                    <span @class(['block font-serif font-semibold tabular-nums', 'text-numeral' => $isFeatured, 'text-hero leading-none' => ! $isFeatured])>{{ Format::number($type->organizations_count) }}</span>
                                    <span @class(['mt-1 text-caption', $isFeatured ? 'text-paper' : $tone['soft']])>kelompok</span>

                                    <span @class(['mt-5 font-serif font-semibold', 'text-display-sm md:text-display' => $isFeatured, 'text-card-title' => ! $isFeatured])>{{ $type->name }}</span>
                                    @if ($type->description ?? null)
                                        <span @class(['mt-2 text-ui', 'max-w-[30rem] text-paper' => $isFeatured, $tone['soft'] => ! $isFeatured])>{{ $type->description }}</span>
                                    @endif

                                    {{--
                                        Avatar kelompok publik (foto produk publik pertama atau inisial), maks 3, lalu +N. Tumpuk 10px,
                                        yang kiri di atas; cincin 2px warna kartu memisahkan lingkaran, inisial digeser ke bagian yang terlihat.
                                    --}}
                                    <span class="mt-5 flex items-center" aria-hidden="true">
                                        @foreach ($type->avatars as $avatar)
                                            <span @class([
                                                'relative flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-tint text-caption font-semibold text-brand ring-2',
                                                $tone['ring'],
                                                ['z-30', 'z-20', 'z-10'][$loop->index],
                                                '-ml-2.5' => ! $loop->first,
                                                'pl-3 tracking-tight' => ! $loop->first && ! $avatar['photo'],
                                            ]) title="{{ $avatar['name'] }}">
                                                @if ($avatar['photo'])
                                                    <x-public.picture :image="$avatar['photo']" alt="" sizes="32px" width="32" height="32" frame="block size-full" class="size-full object-cover" />
                                                @else
                                                    {{ $avatar['initials'] }}
                                                @endif
                                            </span>
                                        @endforeach
                                        @if ($type->more)
                                            <span @class(['relative z-0 -ml-2.5 flex size-8 shrink-0 items-center justify-center rounded-full pl-3 text-caption font-semibold tracking-tight tabular-nums ring-2', $tone['ring'], $tone['more']])>+{{ $type->more }}</span>
                                        @endif
                                    </span>

                                    <span @class(['inline-flex items-center gap-2 whitespace-nowrap text-ui font-semibold underline-offset-4 group-hover:underline group-focus-visible:underline', 'mt-6' => $isFeatured, 'mt-auto pt-6' => ! $isFeatured])>
                                        Lihat kelompok
                                        <x-heroicon-o-arrow-right class="size-4 transition-transform duration-150 group-hover:translate-x-1 group-focus-visible:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <x-public.button variant="outline-brand" :href="route('public.directory')" arrow class="mt-6 w-full sm:hidden">Buka direktori</x-public.button>
            </div>
        </section>
    @endif

    @if ($featured->isNotEmpty())
        <section class="mx-auto max-w-public px-4 py-(--section-y) md:px-6" aria-labelledby="produk-unggulan">
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
            <div class="mx-auto max-w-public px-4 py-(--section-y) md:px-6">
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

    <section class="mx-auto max-w-public px-4 py-(--section-y) md:px-6" aria-labelledby="ajakan">
        <div class="on-dark flex flex-col gap-6 rounded-public bg-forest px-5 py-8 text-paper md:flex-row md:items-center md:px-10">
            <h2 id="ajakan" class="font-serif text-display-sm font-semibold md:max-w-xs">Kelompok Anda belum terdaftar?</h2>
            <p class="flex-1 text-body text-forest-soft md:border-l md:border-forest-line md:pl-8">Hubungi Kantor Distrik Ransiki agar kelompok usaha Anda didata dan profilnya tampil di direktori.</p>
            <x-public.button :href="route('public.contact')" arrow class="self-start md:self-center">Hubungi kami</x-public.button>
        </div>
    </section>
    </div>
</x-layouts.public>
