@use('App\Enums\PostCategory')
@use('App\Support\Format')

{{--
    Berita bergaya majalah: kop editorial cream → bar alat menempel (tab kategori + cari) → Sorotan (halaman 1 tanpa
    filter: berita utama dengan teks di atas foto + dua sorotan) → Agenda kegiatan (deretan geser) → Berita terbaru
    (8 kolom, 2 kolom kartu, 10 per halaman) + Arsip bulan dan Kategori (4 kolom, menempel; HP: pil geser di bawah daftar).
    Logika di App\Support\PostListing; semua filter lewat query string (kategori, q, bulan).
--}}
@php
    $tabs = ['' => 'Semua'] + PostCategory::options();
    $lead = $highlights->first();
    $side = $highlights->slice(1);
    $hasFilter = $listing->isFiltered();
    $panel = 'rounded-tile border border-line bg-paper p-5';
    $sideLink = 'flex min-h-10 items-center justify-between gap-3 rounded-control px-2 -mx-2 text-ui transition-colors hover:bg-subtle hover:text-brand';
@endphp

<x-layouts.public :title="$listing->title()" description="Berita dan kegiatan kelompok usaha dan kelembagaan ekonomi Distrik Ransiki." :canonical="$listing->canonical($posts->currentPage())">
    {{-- Kop editorial: latar cream, judul serif besar, baris tanggal bergaris tipis seperti kop surat kabar. --}}
    <section class="bg-cream">
        <div class="mx-auto max-w-public px-4 pt-8 pb-8 md:px-6 md:pt-12 md:pb-10" data-intro>
            <nav aria-label="Remah roti" class="text-caption text-ink-soft">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control text-brand underline-offset-4 hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><span aria-current="page">Berita</span></li>
                </ol>
            </nav>
            <h1 class="mt-4 font-serif text-hero-sm font-semibold tracking-[-0.01em] text-ink md:text-hero-lg">Kabar Distrik Ransiki</h1>
            <p class="mt-5 border-y border-ink/20 py-2.5 text-ui text-ink-soft">
                Berita dan kegiatan terbaru dari Distrik Ransiki <span aria-hidden="true">·</span> <time datetime="{{ now()->toDateString() }}">{{ Format::dayDate(now()) }}</time>
            </p>
        </div>
    </section>

    {{-- Bar alat menempel di bawah header: tab kategori (jumlah mengikuti cari dan bulan) dan cari judul. --}}
    <div class="sticky top-13 z-30 border-b border-line bg-paper md:top-15 print:hidden">
        <div class="mx-auto flex max-w-public flex-col gap-3 px-4 max-md:pb-3 md:flex-row md:items-center md:justify-between md:px-6">
            <nav aria-label="Kategori berita" class="scrollbar-none -mb-px overflow-x-auto max-md:border-b max-md:border-line">
                <ul class="flex gap-6">
                    @foreach ($tabs as $value => $label)
                        @php $current = ($listing->category?->value ?? '') === $value; @endphp
                        <li>
                            <a
                                href="{{ $listing->url(['kategori' => $value ?: null]) }}"
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'inline-flex h-13 items-center gap-2 border-b-2 whitespace-nowrap text-ui transition-colors md:h-14',
                                    'border-brand font-semibold text-ink' => $current,
                                    'border-transparent text-ink-soft hover:text-ink' => ! $current,
                                ])
                            >
                                {{ $label }}
                                <span class="rounded-full bg-subtle px-2 text-caption text-ink-soft tabular-nums">{{ Format::number($counts[$value]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <form novalidate method="GET" action="{{ route('public.posts') }}" role="search" class="flex gap-2 md:w-88">
                @if ($listing->category)
                    <input type="hidden" name="kategori" value="{{ $listing->category->value }}">
                @endif
                @if ($listing->month)
                    <input type="hidden" name="bulan" value="{{ $listing->month->format('Y-m') }}">
                @endif
                <label for="q" class="sr-only">Cari judul berita</label>
                <x-input type="search" name="q" id="q" :value="$listing->q" placeholder="Cari judul berita" class="min-w-0 flex-1" />
                <x-public.button type="submit" variant="brand" size="md" icon="magnifying-glass">Cari</x-public.button>
            </form>
        </div>
    </div>

    @if ($lead)
        {{-- Sorotan: berita utama 8 kolom (teks di atas foto, overlay forest dari bawah) + dua sorotan bertumpuk 4 kolom. --}}
        <section class="mx-auto max-w-public px-4 pt-8 md:px-6 md:pt-12" aria-labelledby="sorotan">
            <h2 id="sorotan" class="sr-only">Sorotan</h2>
            <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                @php $leadCover = $lead->coverSources(); @endphp
                <article class="group on-dark relative isolate flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-tile bg-forest text-paper sm:aspect-[16/10] lg:col-span-8 has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand" x-reveal="fade-up">
                    @if ($leadCover)
                        <x-public.picture :image="$leadCover" alt="" eager sizes="(min-width: 64rem) 44rem, 100vw" width="960" height="600" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
                        <div class="overlay-band-bottom absolute inset-0 -z-10" aria-hidden="true"></div>
                    @endif
                    <div class="p-5 md:p-8">
                        <x-public.pill tone="dark">{{ $lead->category->label() }}</x-public.pill>
                        <h3 class="mt-3 font-serif text-display-sm font-semibold text-balance md:text-hero-sm">
                            <a href="{{ route('public.post', $lead->slug) }}" class="after:absolute after:inset-0 focus-visible:outline-none group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4">{{ $lead->title }}</a>
                        </h3>
                        @if ($leadSummary = $lead->summary ?: Format::excerpt($lead->body, 200))
                            <p class="mt-3 line-clamp-2 max-w-[40rem] text-body text-paper/85 max-sm:hidden">{{ $leadSummary }}</p>
                        @endif
                        <p class="mt-3 text-caption text-paper/85">
                            <time datetime="{{ $lead->published_at->toAtomString() }}">{{ Format::date($lead->published_at) }}</time>
                            <span aria-hidden="true">·</span> {{ $lead->readingMinutes() }} menit baca
                        </p>
                    </div>
                </article>

                @if ($side->isNotEmpty())
                    <ul class="grid gap-5 lg:col-span-4 lg:gap-8" x-reveal.stagger="fade-up">
                        @foreach ($side as $post)
                            @php $cover = $post->coverSources(); @endphp
                            <li class="min-w-0">
                                {{-- HP: baris dengan thumbnail di kiri; mulai 1024px: foto 16:10 di atas judul. --}}
                                <article class="group relative grid grid-cols-[7rem_minmax(0,1fr)] items-start gap-4 sm:grid-cols-[10rem_minmax(0,1fr)] lg:grid-cols-1 lg:gap-0 has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand">
                                    <div class="aspect-[16/10] overflow-hidden rounded-photo bg-cream lg:rounded-tile">
                                        @if ($cover)
                                            <x-public.picture :image="$cover" alt="" sizes="(min-width: 64rem) 21rem, 10rem" width="480" height="300" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
                                        @else
                                            <span class="flex size-full items-center justify-center text-clay" aria-hidden="true"><x-dynamic-component :component="'heroicon-o-'.$post->category->icon()" class="size-8 lg:size-12" /></span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 lg:mt-4">
                                        @include('publik.posts._meta', ['post' => $post, 'short' => true])
                                        <h3 class="mt-1.5 line-clamp-3 font-serif text-card-title font-semibold lg:text-title-sm">
                                            <a href="{{ route('public.post', $post->slug) }}" class="text-ink after:absolute after:inset-0 focus-visible:outline-none group-hover:text-brand">{{ $post->title }}</a>
                                        </h3>
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    @if ($agenda->isNotEmpty())
        {{--
            Agenda kegiatan (ada kegiatan setelah hari ini: yang akan datang dulu) atau Kegiatan terbaru: deretan geser
            (scroll-snap), panah mulai 1024px; HP kartu masuk dari kanan (data-reveal-row). Blok tanggal = tanggal kegiatan,
            tanpa itu tanggal terbit.
        --}}
        <section class="mx-auto mt-section max-w-public px-4 md:px-6" aria-labelledby="agenda" x-data="scrollRow">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="agenda" class="font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink">{{ $agendaUpcoming ? 'Agenda kegiatan' : 'Kegiatan terbaru' }}</h2>
                    <p class="mt-1 text-ui text-ink-soft">{{ $agendaUpcoming ? 'Kegiatan kelompok dan pemerintah distrik, yang akan datang lebih dulu.' : 'Kegiatan kelompok dan pemerintah distrik, terbaru lebih dulu.' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('public.posts', ['kategori' => PostCategory::Activity->value]) }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-control text-ui font-semibold text-brand underline-offset-4 hover:underline max-sm:hidden">Semua kegiatan<x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
                    @if ($agenda->count() > 1)
                        @foreach (['prev' => ['chevron-left', 'Sebelumnya'], 'next' => ['chevron-right', 'Berikutnya']] as $direction => [$icon, $label])
                            <button type="button" x-on:click="move({{ $direction === 'prev' ? -1 : 1 }})" x-bind:disabled="{{ $direction === 'prev' ? 'atStart' : 'atEnd' }}" class="flex size-11 items-center justify-center rounded-full border border-line-strong bg-paper text-ink transition-colors hover:border-brand hover:text-brand disabled:cursor-default disabled:border-line disabled:text-ink-muted disabled:hover:border-line max-lg:hidden">
                                <span class="sr-only">Kegiatan {{ mb_strtolower($label) }}</span><x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" aria-hidden="true" />
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>
            <ul x-ref="track" x-reveal.stagger="fade-up" data-reveal-row class="scrollbar-none -mx-4 mt-5 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:scroll-px-0 md:gap-5 md:px-0">
                @foreach ($agenda as $post)
                    @php
                        $cover = $post->coverSources();
                        $place = $post->organizations->first();
                        $day = $post->eventDay();
                    @endphp
                    <li class="w-[72vw] max-w-72 shrink-0 snap-start md:w-[calc((100%-2.5rem)/2.6)] md:max-w-none lg:w-[calc((100%-3.75rem)/4)]">
                        <article class="group relative flex h-full flex-col has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-4 has-[a:focus-visible]:outline-brand">
                            <div class="relative aspect-[4/3] overflow-hidden rounded-tile bg-cream">
                                @if ($cover)
                                    <x-public.picture :image="$cover" alt="" sizes="(min-width: 64rem) 16rem, 72vw" width="480" height="360" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
                                @else
                                    <span class="flex size-full items-center justify-center text-clay" aria-hidden="true"><x-heroicon-o-calendar-days class="size-12" /></span>
                                @endif
                                {{-- Blok tanggal kalender 52 × 56px (HP 46 × 50px), putih 95%, sudut 12px. --}}
                                <time datetime="{{ $day->toDateString() }}" class="absolute top-3 left-3 flex h-[50px] w-[46px] flex-col items-center justify-center gap-1 rounded-photo bg-paper/95 shadow-soft md:h-14 md:w-13">
                                    <span class="font-serif text-card-title leading-none font-semibold text-ink tabular-nums md:text-title-sm md:leading-none">{{ $day->format('j') }}</span>
                                    <span class="text-[10px] leading-none font-semibold tracking-[0.06em] text-clay uppercase">{{ $day->translatedFormat('M') }}</span>
                                </time>
                            </div>
                            {{-- Judul 2 baris (tinggi tetap) agar baris lokasi sejajar antarkartu. --}}
                            <h3 class="mt-3 line-clamp-2 min-h-[3.25rem] font-serif text-card-title font-semibold">
                                <a href="{{ route('public.post', $post->slug) }}" class="text-ink after:absolute after:inset-0 focus-visible:outline-none group-hover:text-brand">{{ $post->title }}</a>
                            </h3>
                            @if ($place)
                                <p class="mt-2 flex min-w-0 items-center gap-1.5 text-caption text-ink" title="{{ $place->village->name }}">
                                    <x-heroicon-o-map-pin class="size-4 shrink-0 text-brand" aria-hidden="true" /><span class="truncate">{{ $place->village->name }}</span>
                                </p>
                                <p class="mt-0.5 truncate pl-5.5 text-caption text-ink-soft" title="{{ $place->name }}">{{ $place->name }}</p>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Berita terbaru (8 kolom) + Arsip dan Kategori (4 kolom, menempel; HP: pil geser di bawah daftar). --}}
    <div class="mx-auto mt-section max-w-public px-4 pb-section md:px-6 lg:grid lg:grid-cols-12 lg:gap-12">
        <section class="min-w-0 lg:col-span-8" aria-labelledby="terbaru">
            <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                <h2 id="terbaru" class="font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink">{{ $hasFilter ? $listing->title() : 'Berita terbaru' }}</h2>
                @if ($hasFilter)
                    <p class="text-ui text-ink-soft" aria-live="polite">
                        <span class="font-semibold text-ink tabular-nums">{{ Format::number($posts->total()) }}</span> berita
                        <span aria-hidden="true">·</span>
                        <a href="{{ route('public.posts') }}" class="font-semibold text-brand underline underline-offset-4 hover:text-brand-hover">Hapus filter</a>
                    </p>
                @endif
            </div>

            @if ($posts->isNotEmpty())
                <ul class="mt-6 grid gap-x-8 gap-y-10 sm:grid-cols-2" x-reveal.stagger="fade-up">
                    @foreach ($posts as $post)
                        <li class="min-w-0">@include('publik._post-card', ['post' => $post])</li>
                    @endforeach
                </ul>
                @if ($posts->hasPages())
                    <div class="mt-section-head">{{ $posts->onEachSide(1)->links('publik._pagination') }}</div>
                @endif
            @elseif ($highlights->isEmpty())
                <div class="mt-6 rounded-panel bg-cream px-6 py-12 text-center md:py-16">
                    <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-paper text-clay" aria-hidden="true">
                        <x-heroicon-o-newspaper class="size-8 stroke-1" />
                    </span>
                    <h3 class="mt-5 font-serif text-page-title font-semibold text-ink">
                        @if ($listing->q !== '')
                            Tidak ada berita yang cocok
                        @elseif ($hasFilter)
                            Belum ada {{ $listing->category ? mb_strtolower($listing->category->label()) : 'berita' }} di sini
                        @else
                            Belum ada berita yang diterbitkan
                        @endif
                    </h3>
                    <p class="mx-auto mt-3 max-w-[28rem] text-ui text-ink-soft">
                        {{ $hasFilter ? 'Coba kata lain, bulan lain, atau lihat semua berita dan kegiatan.' : 'Berita dan kegiatan tampil di sini setelah diterbitkan Admin distrik.' }}
                    </p>
                    @if ($hasFilter)
                        <x-public.pill-link :href="route('public.posts')" class="mt-6">Lihat semua berita</x-public.pill-link>
                    @endif
                </div>
            @else
                <p class="mt-6 text-body text-ink-soft">Semua berita sudah tampil di sorotan.</p>
            @endif
        </section>

        @if ($archive)
            {{-- Desktop: panel menempel di kanan. --}}
            <aside class="hidden lg:col-span-4 lg:block" aria-label="Arsip dan kategori">
                <div class="sticky top-36 space-y-6">
                    <section class="{{ $panel }}" aria-labelledby="arsip">
                        <h2 id="arsip" class="font-serif text-card-title font-semibold text-ink">Arsip</h2>
                        <ul class="mt-3 max-h-[22rem] overflow-y-auto">
                            @foreach ($archive as $month)
                                @php $active = $listing->month?->format('Y-m') === $month['value']; @endphp
                                <li>
                                    <a href="{{ $listing->url(['bulan' => $active ? null : $month['value'], 'q' => null]) }}" @if ($active) aria-current="page" @endif @class([$sideLink, 'font-semibold text-brand' => $active, 'text-ink' => ! $active])>
                                        {{ $month['label'] }}
                                        <span class="text-caption text-ink-muted tabular-nums">{{ Format::number($month['count']) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                    <section class="{{ $panel }}" aria-labelledby="kategori-panel">
                        <h2 id="kategori-panel" class="font-serif text-card-title font-semibold text-ink">Kategori</h2>
                        <ul class="mt-3">
                            @foreach ($tabs as $value => $label)
                                @php $active = ($listing->category?->value ?? '') === $value; @endphp
                                <li>
                                    <a href="{{ $listing->url(['kategori' => $value ?: null]) }}" @if ($active) aria-current="page" @endif @class([$sideLink, 'font-semibold text-brand' => $active, 'text-ink' => ! $active])>
                                        {{ $label }}
                                        <span class="text-caption text-ink-muted tabular-nums">{{ Format::number($counts[$value]) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </div>
            </aside>

            {{-- HP dan tablet: arsip sebagai pil yang bisa digeser, di bawah daftar. --}}
            <section class="mt-section-head lg:hidden" aria-labelledby="arsip-hp">
                <h2 id="arsip-hp" class="font-serif text-card-title font-semibold text-ink">Arsip</h2>
                <ul class="scrollbar-none -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0">
                    @foreach ($archive as $month)
                        @php $active = $listing->month?->format('Y-m') === $month['value']; @endphp
                        <li class="shrink-0">
                            <a href="{{ $listing->url(['bulan' => $active ? null : $month['value'], 'q' => null]) }}" @if ($active) aria-current="page" @endif @class([
                                'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-ui font-semibold whitespace-nowrap transition-colors',
                                'border-brand bg-brand text-paper' => $active,
                                'border-line-strong bg-paper text-ink hover:border-brand' => ! $active,
                            ])>
                                {{ $month['label'] }}<span class="text-caption font-normal tabular-nums opacity-80">{{ Format::number($month['count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.public>
