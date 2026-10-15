@use('App\Support\Format')
@use('App\Support\ProductSearch')
@use('App\Support\PublicIcons')

{{--
    Katalog Produk unggulan: banner forest ringkas + kotak cari melayang (sama dengan Direktori), pil kategori bidang
    usaha (geser di HP), kampung + urutan, deretan "Pilihan distrik" (halaman 1 tanpa filter), grid 2/3/4 kolom,
    paginasi 16. Satu form GET (jalan tanpa JS); dengan JS dimuat ulang otomatis oleh Alpine "directory"
    (resources/js/directory.js) yang menukar [data-swap] dan angka [data-facet].
--}}
@php
    $found = $products->total();
@endphp

<x-layouts.public :title="$search->title()" :description="$search->description($found)" :canonical="$search->canonical($products->currentPage())">
<form
    method="GET"
    action="{{ route('public.products') }}"
    role="search"
    aria-label="Cari dan saring produk"
    x-data="directory"
    x-ref="form"
    class="reveal-quick"
    data-default-sort="pilihan"
    x-on:submit.prevent="submit()"
    x-on:change="submit()"
    x-on:click="navigate($event)"
>
    {{-- Banner: 180px HP, 240px desktop; ruang bawah = setengah kotak cari (28px) + 24px. --}}
    <section class="on-dark relative bg-forest text-paper">
        <div class="mx-auto flex min-h-45 max-w-public flex-col justify-end px-4 pt-10 pb-13 md:min-h-60 md:px-6" data-intro>
            <nav aria-label="Remah roti" class="mb-3 text-caption text-forest-soft">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control text-paper underline-offset-4 hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><span aria-current="page">Produk</span></li>
                </ol>
            </nav>
            <h1 class="font-serif text-display-sm font-semibold tracking-[-0.01em] md:text-title-lg">Produk unggulan</h1>
            <p class="mt-2 max-w-[40rem] text-body text-paper">Hasil kebun, laut, dan olahan kelompok usaha di Distrik Ransiki.</p>
            <p class="mt-1 text-ui text-forest-soft"><span class="font-semibold text-paper tabular-nums">{{ Format::number($total) }}</span> produk</p>
        </div>

        <div class="absolute inset-x-0 bottom-0 z-10 translate-y-1/2">
            <div class="mx-auto max-w-public px-4 md:px-6">
                <div data-intro-search class="flex h-14 max-w-3xl items-center gap-2 rounded-tile border border-line bg-paper pr-1.5 pl-4 shadow-strip focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand">
                    <x-heroicon-o-magnifying-glass class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
                    <label for="q" class="sr-only">Cari nama produk, kelompok, atau kampung</label>
                    <input
                        type="search"
                        id="q"
                        name="q"
                        value="{{ $search->q }}"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Cari nama produk, kelompok, atau kampung"
                        class="h-full min-w-0 flex-1 bg-transparent text-body text-ink placeholder:text-ink-muted focus:outline-none"
                        x-on:input="typed()"
                        x-on:change.stop
                    >
                    <button type="submit" class="inline-flex h-11 shrink-0 items-center rounded-[12px] bg-brand px-5 text-ui font-semibold text-paper transition-colors hover:bg-brand-hover">Cari</button>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-public px-4 pt-[calc(1.75rem+2rem)] pb-section md:px-6">
        {{-- Pil kategori: radio bergaya pil (satu bidang), digeser horizontal di HP. Angka mengikuti cari + kampung. --}}
        @if ($sectors->isNotEmpty())
            <fieldset class="min-w-0">
                <legend class="sr-only">Bidang usaha</legend>
                <div class="scrollbar-none -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:overflow-visible md:px-0">
                    @foreach ([(object) ['id' => null, 'name' => 'Semua', 'count' => $allCount], ...$sectors] as $option)
                        <label class="group/pill relative shrink-0" data-empty="{{ $option->count === 0 ? 'true' : 'false' }}">
                            <input type="radio" name="bidang" value="{{ $option->id }}" @checked($search->sector === $option->id) class="peer sr-only">
                            <span class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border border-line-strong bg-paper px-4 text-ui font-semibold text-ink transition-colors hover:border-brand peer-checked:border-brand peer-checked:bg-brand peer-checked:text-paper peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand group-data-[empty=true]/pill:text-ink-muted group-data-[empty=true]/pill:peer-checked:text-paper md:min-h-10">
                                @if ($option->id)
                                    <x-public.icon :name="PublicIcons::sector($option->name)" class="size-4 shrink-0" />
                                @endif
                                {{ $option->name }}
                                <span class="text-caption font-normal tabular-nums opacity-80" data-facet="bidang-{{ $option->id ?? 'semua' }}"><span class="sr-only">, </span>{{ Format::number($option->count) }}<span class="sr-only"> produk</span></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        {{-- Bar hasil: jumlah (dibacakan saat berubah), kampung, urutan. --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 scroll-mt-24" x-ref="results">
            <p id="ringkasan" data-swap role="status" class="min-w-0 text-ui text-ink-soft max-sm:w-full">
                <span class="font-semibold text-ink tabular-nums">{{ Format::number($found) }}</span> produk ditemukan
                @if ($search->q !== '')
                    untuk <span class="font-semibold text-ink">"{{ $search->q }}"</span>
                @endif
            </p>
            {{-- HP: kampung dan urutan bertumpuk selebar layar agar teks pilihan tidak terpotong. --}}
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:items-center">
                <div class="min-w-0">
                    <label for="kampung" class="sr-only">Kampung</label>
                    <x-select id="kampung" name="kampung" size="sm" :options="$villages" :selected="$search->village" placeholder="Semua kampung" class="sm:min-w-44" />
                </div>
                <div class="min-w-0">
                    <label for="urut" class="sr-only">Urutkan</label>
                    <x-select id="urut" name="urut" size="sm" :options="ProductSearch::SORTS" :selected="$search->sort" class="sm:min-w-48" />
                </div>
            </div>
        </div>
        <noscript><x-public.button type="submit" variant="brand" class="mt-4">Terapkan filter</x-public.button></noscript>

        {{-- Pilihan distrik: halaman 1 tanpa cari/filter; geser dengan scroll-snap, panah mulai 1024px. --}}
        <div id="pilihan-distrik" data-swap>
            @if ($featured->isNotEmpty())
                <section class="mt-8 md:mt-10" aria-labelledby="judul-pilihan" x-data="scrollRow">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <h2 id="judul-pilihan" class="font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink">Pilihan distrik</h2>
                            <p class="mt-1 text-ui text-ink-soft">Produk yang dipilih Admin distrik untuk dikenalkan lebih dulu.</p>
                        </div>
                        @if ($featured->count() > 1)
                            <div class="flex gap-2 max-lg:hidden">
                                @foreach (['prev' => ['chevron-left', 'Sebelumnya'], 'next' => ['chevron-right', 'Berikutnya']] as $direction => [$icon, $label])
                                    <button type="button" x-on:click="move({{ $direction === 'prev' ? -1 : 1 }})" x-bind:disabled="{{ $direction === 'prev' ? 'atStart' : 'atEnd' }}" class="flex size-11 items-center justify-center rounded-full border border-line-strong bg-paper text-ink transition-colors hover:border-brand hover:text-brand disabled:cursor-default disabled:border-line disabled:text-ink-muted disabled:hover:border-line">
                                        <span class="sr-only">{{ $label }}</span><x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" aria-hidden="true" />
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <ul x-ref="track" x-reveal.stagger.quick="fade-up" class="scrollbar-none -mx-4 mt-5 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:scroll-px-0 md:gap-5 md:px-0">
                        @foreach ($featured as $product)
                            <li class="w-[72vw] max-w-80 shrink-0 md:max-w-none snap-start md:w-[calc((100%-2.5rem)/2.6)] lg:w-[calc((100%-2.5rem)/3)]">
                                @include('publik.products._card', ['large' => true])
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div id="hasil" data-swap class="mt-8 md:mt-10" x-ref="hasil" x-bind:aria-busy="loading">
            @if ($products->isEmpty())
                @if ($search->isFiltered())
                    <div class="flex flex-col items-center rounded-panel bg-cream px-6 py-14 text-center md:py-20">
                        <span class="flex size-20 items-center justify-center rounded-full bg-paper text-clay" aria-hidden="true">
                            <x-heroicon-o-magnifying-glass class="size-9 stroke-1" />
                        </span>
                        <h2 class="mt-6 font-serif text-display-sm font-semibold text-ink">Tidak ada produk yang cocok</h2>
                        <ul class="mt-3 space-y-1 text-body text-ink-soft">
                            @if ($search->q !== '')<li>Periksa ejaan kata pencarian atau pakai kata yang lebih umum.</li>@endif
                            @if ($search->sector || $search->village)<li>Pilih bidang usaha atau kampung lain.</li>@endif
                        </ul>
                        <x-public.pill-link :href="route('public.products')" data-directory-link class="mt-8">Hapus semua filter</x-public.pill-link>
                    </div>
                @else
                    <x-empty-state title="Belum ada produk yang dipublikasikan" description="Produk tampil di sini setelah kelompok mempublikasikannya." />
                @endif
            @else
                @if ($featured->isNotEmpty())
                    <h2 class="mb-5 font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink">Semua produk</h2>
                @endif
                <ul data-cards x-reveal.stagger.quick="fade-up" class="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 md:gap-x-5 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <li class="min-w-0">@include('publik.products._card', ['sectorId' => $search->sector])</li>
                    @endforeach
                </ul>

                @if ($products->hasPages())
                    <div class="mt-section-head" data-directory-pages>{{ $products->onEachSide(1)->links('publik._pagination') }}</div>
                @endif
            @endif
        </div>
    </div>
</form>
</x-layouts.public>
