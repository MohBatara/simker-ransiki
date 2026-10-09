@use('App\Support\Format')

<x-layouts.public :description="$settings['home_intro'] ?? 'Direktori kelompok dan kelembagaan ekonomi Distrik Ransiki: profil kelompok, produk, dan kontak.'">
    <section class="grid gap-10 md:grid-cols-[minmax(0,1fr)_15rem] md:gap-14">
        <div>
            <p class="text-caption text-ink-muted">Direktori kelembagaan ekonomi</p>
            <h1 class="mt-2 text-display-sm font-semibold text-ink md:text-display">Kelompok usaha dan kelembagaan ekonomi Distrik Ransiki</h1>
            @if ($settings['home_intro'])
                <p class="mt-4 max-w-[42rem] text-body text-ink-soft">{{ $settings['home_intro'] }}</p>
            @endif
            <form method="GET" action="{{ route('public.directory') }}" role="search" class="mt-6 flex max-w-xl gap-2">
                <label for="q" class="sr-only">Cari kelompok atau produk</label>
                <x-input name="q" placeholder="Cari nama kelompok atau produk, mis. kakao" class="min-w-0 flex-1 md:h-12!" />
                <x-button type="submit" icon="magnifying-glass" class="md:h-12!">Cari</x-button>
            </form>
        </div>

        <div>
            @include('publik._stats', ['class' => 'md:grid-cols-1 md:gap-0 md:divide-y md:divide-line md:border-y md:border-line', 'itemClass' => 'md:py-4'])
            @if ($stats['updated_at'])
                <p class="mt-3 text-caption text-ink-muted">Data diperbarui {{ Format::date($stats['updated_at']) }}</p>
            @endif
        </div>
    </section>

    @if ($settings['cover_photo_path'])
        <figure class="mt-10">
            <img
                src="{{ route('public.cover', ['v' => substr(md5($settings['cover_photo_path']), 0, 8)]) }}"
                alt="{{ $settings['cover_caption'] ?? 'Foto Distrik Ransiki' }}"
                width="1600" height="700" decoding="async"
                class="aspect-[16/7] w-full rounded-card border border-line object-cover"
            >
            @if ($settings['cover_caption'] || $settings['cover_credit'])
                <figcaption class="mt-2 text-caption text-ink-muted">
                    {{ $settings['cover_caption'] }}@if ($settings['cover_caption'] && $settings['cover_credit']) · @endif @if ($settings['cover_credit'])Foto: {{ $settings['cover_credit'] }}@endif
                </figcaption>
            @endif
        </figure>
    @endif

    <section class="mt-12">
        @include('publik._section-heading', ['title' => 'Telusuri per bidang usaha'])
        @if ($sectors->isEmpty())
            <p class="mt-4 text-body text-ink-soft">Belum ada kelompok yang dipublikasikan.</p>
        @else
            <ul class="mt-4 grid gap-px overflow-hidden rounded-card border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sectors as $sector)
                    <li class="bg-paper">
                        <a href="{{ route('public.directory', ['bidang' => $sector->id]) }}" class="flex h-full items-center justify-between gap-3 px-4 py-4 transition-colors hover:bg-subtle">
                            <span>
                                <span class="block font-semibold text-ink">{{ $sector->name }}</span>
                                <span class="text-caption text-ink-soft tabular-nums">{{ Format::number($sector->organizations_count) }} kelompok</span>
                            </span>
                            <x-heroicon-o-chevron-right class="size-4 shrink-0 text-ink-muted" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($featured->isNotEmpty())
        <section class="mt-12">
            @include('publik._section-heading', ['title' => 'Produk unggulan', 'link' => ['href' => route('public.products'), 'label' => 'Lihat semua produk']])
            <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
                @foreach ($featured as $product)
                    @include('publik._product-card')
                @endforeach
            </div>
        </section>
    @endif

    @if ($excerpt = Format::excerpt($settings['profile_body']))
        <section class="mt-12">
            @include('publik._section-heading', ['title' => 'Tentang Distrik Ransiki'])
            <p class="mt-4 max-w-[42rem] text-body text-ink-soft">{{ $excerpt }}</p>
            <a href="{{ route('public.profile') }}" class="mt-3 inline-block rounded-control text-brand hover:text-brand-hover">Baca profil lengkap</a>
        </section>
    @endif
</x-layouts.public>
