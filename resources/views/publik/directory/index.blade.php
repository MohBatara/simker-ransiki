@use('App\Support\DirectorySearch')
@use('App\Support\Format')

{{--
    Direktori kelompok: banner forest ringkas + kotak cari besar yang menumpang setengah di bawahnya; filter berfacet
    (desktop panel samping sticky, HP/tablet bottom sheet), bar hasil (jumlah, urutan, Kartu/Daftar), chip filter aktif,
    kartu, paginasi. Semua isian di satu form GET (bisa dibagikan, jalan tanpa JS); dengan JS hasil dimuat ulang otomatis
    (Alpine "directory", resources/js/directory.js) dengan menukar elemen [data-swap] dan angka [data-facet].
--}}
@php
    $found = $organizations->total();
    $filterCount = $search->activeFilterCount();
@endphp

<x-layouts.public :title="$search->title()" :description="$search->description($found)" :canonical="$search->canonical($organizations->currentPage())">
<form
    method="GET"
    action="{{ route('public.directory') }}"
    role="search"
    aria-label="Cari dan saring kelompok"
    x-data="directory"
    x-ref="form"
    data-view="grid"
    x-bind:data-view="view"
    x-on:submit.prevent="submit(); sheet = false"
    x-on:change="submit()"
    x-on:click="navigate($event)"
>
    {{-- Banner: 180px HP, 240px desktop; ruang bawah = setengah kotak cari (28px) + 24px. --}}
    <section class="on-dark relative bg-forest text-paper">
        <div class="mx-auto flex min-h-45 max-w-public flex-col justify-end px-4 pt-10 pb-13 md:min-h-60 md:px-6">
            <nav aria-label="Remah roti" class="mb-3 text-caption text-forest-soft">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control text-paper underline-offset-4 hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><span aria-current="page">Direktori</span></li>
                </ol>
            </nav>
            <h1 class="font-serif text-display-sm font-semibold tracking-[-0.01em] md:text-title-lg">Direktori kelompok</h1>
            <p class="mt-2 max-w-[40rem] text-body text-paper">Temukan kelompok usaha, koperasi, dan BUMDes di Distrik Ransiki.</p>
            <p class="mt-1 text-ui text-forest-soft"><span class="font-semibold text-paper tabular-nums">{{ Format::number($total) }}</span> kelompok terdaftar</p>
        </div>

        <div class="absolute inset-x-0 bottom-0 z-10 translate-y-1/2">
            <div class="mx-auto max-w-public px-4 md:px-6">
                <div class="flex h-14 max-w-3xl items-center gap-2 rounded-tile border border-line bg-paper pr-1.5 pl-4 shadow-strip focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand">
                    <x-heroicon-o-magnifying-glass class="size-5 shrink-0 text-ink-muted" aria-hidden="true" />
                    <label for="q" class="sr-only">Cari nama kelompok, produk, atau kampung</label>
                    <input
                        type="search"
                        id="q"
                        name="q"
                        value="{{ $search->q }}"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Cari nama kelompok, produk, atau kampung"
                        class="h-full min-w-0 flex-1 bg-transparent text-body text-ink placeholder:text-ink-muted focus:outline-none"
                        x-on:input="typed()"
                        x-on:change.stop
                    >
                    <button type="submit" class="inline-flex h-11 shrink-0 items-center rounded-[12px] bg-brand px-5 text-ui font-semibold text-paper transition-colors hover:bg-brand-hover">Cari</button>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-public px-4 pt-[calc(1.75rem+2rem)] pb-section md:px-6 lg:grid lg:grid-cols-[17.5rem_minmax(0,1fr)] lg:gap-10">
        {{-- Latar bottom sheet (HP dan tablet). --}}
        <div x-cloak x-show="sheet" x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 lg:hidden" x-on:click="close()" aria-hidden="true"></div>

        {{-- Panel filter: panel samping sticky mulai 1024px; di bawahnya bottom sheet (dialog, fokus terkunci). --}}
        <aside
            id="filter-panel"
            x-ref="sheet"
            tabindex="-1"
            aria-labelledby="filter-judul"
            class="max-lg:hidden max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-50 max-lg:flex max-lg:max-h-[85dvh] max-lg:flex-col max-lg:rounded-t-[20px] max-lg:bg-paper max-lg:shadow-float lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:self-start lg:overflow-y-auto"
            x-bind:class="{ 'max-lg:hidden': ! sheet }"
            x-bind:role="sheet ? 'dialog' : null"
            x-bind:aria-modal="sheet ? 'true' : null"
            x-bind:style="drag ? `transform: translateY(${drag}px)` : ''"
            x-on:keydown.escape="close()"
            x-on:keydown.tab="trap($event)"
        >
            <div class="shrink-0 px-4 pt-2 lg:hidden" x-on:touchstart="dragStart($event)" x-on:touchmove="dragMove($event)" x-on:touchend="dragEnd()">
                <span class="mx-auto block h-1.5 w-10 rounded-full bg-line-strong" aria-hidden="true"></span>
            </div>
            <div class="flex shrink-0 items-center justify-between gap-3 px-4 pt-2 pb-1 lg:px-0 lg:pt-0">
                <h2 id="filter-judul" class="font-serif text-card-title font-semibold text-ink">Filter</h2>
                <button type="button" x-on:click="close()" class="-mr-2 flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink lg:hidden">
                    <span class="sr-only">Tutup filter</span>
                    <x-heroicon-o-x-mark class="size-6" aria-hidden="true" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 lg:overflow-visible lg:px-0">
                @foreach (DirectorySearch::DIMENSIONS as $dimension => $label)
                    @php
                        $options = $facets[$dimension];
                        // Kotak cari kecil hanya untuk Kampung dengan lebih dari 8 opsi.
                        $searchable = $dimension === 'kampung' && $options->count() > 8;
                    @endphp
                    <details open class="group/facet border-b border-line py-1" x-data="{ term: '' }">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-control text-ui font-semibold text-ink [&::-webkit-details-marker]:hidden">
                            {{ $label }}
                            <x-heroicon-o-chevron-down class="size-4 text-ink-muted transition-transform group-open/facet:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
                        </summary>
                        @if ($searchable)
                            <div class="relative mb-2">
                                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
                                <input type="search" x-model="term" x-on:change.stop placeholder="Cari {{ lcfirst($label) }}" aria-label="Cari {{ lcfirst($label) }}" class="h-11 w-full rounded-control border border-line-input bg-paper pr-3 pl-9 text-ui text-ink placeholder:text-ink-muted md:h-9">
                            </div>
                        @endif
                        <ul class="pb-2">
                            @foreach ($options as $option)
                                <li
                                    class="group/option"
                                    data-empty="{{ $option->count === 0 ? 'true' : 'false' }}"
                                    @if ($searchable) x-show="! term || @js(mb_strtolower($option->name)).includes(term.trim().toLowerCase())" @endif
                                >
                                    <label class="-mx-2 flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-2 hover:bg-subtle md:min-h-10">
                                        <span class="relative flex size-5 shrink-0">
                                            <input
                                                type="checkbox"
                                                name="{{ $dimension }}[]"
                                                value="{{ $option->id }}"
                                                @checked($option->checked)
                                                @if ($loop->parent->first && $loop->first) data-sheet-first @endif
                                                class="peer size-5 cursor-pointer appearance-none rounded-control border border-line-input bg-paper transition-colors checked:border-brand checked:bg-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                            >
                                            <x-heroicon-o-check class="pointer-events-none absolute inset-0.5 hidden size-4 stroke-2 text-paper peer-checked:block" />
                                        </span>
                                        <span class="min-w-0 flex-1 text-ui text-ink group-data-[empty=true]/option:text-ink-muted">{{ $option->name }}</span>
                                        <span class="text-caption text-ink-muted tabular-nums" data-facet="{{ $dimension }}-{{ $option->id }}"><span class="sr-only">, </span>{{ Format::number($option->count) }}<span class="sr-only"> kelompok</span></span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach

                <div id="hapus-filter" data-swap class="py-3 max-lg:hidden">
                    @if ($filterCount)
                        <button type="button" x-on:click="clearFilters()" class="inline-flex min-h-10 items-center gap-1.5 rounded-control text-ui font-semibold text-brand underline-offset-4 hover:underline">
                            <x-heroicon-o-x-mark class="size-4" aria-hidden="true" />Hapus semua filter
                        </button>
                    @endif
                </div>
                <noscript><x-public.button type="submit" variant="brand" class="mt-2 w-full max-lg:hidden">Terapkan filter</x-public.button></noscript>
            </div>

            <div class="flex shrink-0 items-center gap-3 border-t border-line px-4 py-3 lg:hidden">
                <button type="button" x-on:click="clearFilters()" class="inline-flex min-h-11 items-center rounded-control px-2 text-ui font-semibold text-brand">Hapus semua</button>
                <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-full bg-forest px-5 text-ui font-semibold text-paper transition-colors hover:bg-brand-hover">
                    Tampilkan&nbsp;<span id="jumlah-tombol" data-swap>{{ Format::number($found) }}</span>&nbsp;kelompok
                </button>
            </div>
        </aside>

        <div class="min-w-0" x-ref="results">
            {{-- Bar hasil: jumlah (dibacakan saat berubah), tombol Filter (HP/tablet), urutan, Kartu/Daftar. --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p id="ringkasan" data-swap role="status" class="min-w-0 text-ui text-ink-soft max-sm:w-full">
                    <span class="font-semibold text-ink tabular-nums">{{ Format::number($found) }}</span> kelompok ditemukan
                    @if ($search->q !== '')
                        untuk <span class="font-semibold text-ink">"{{ $search->q }}"</span>
                    @endif
                </p>
                <div class="flex items-center gap-2 max-sm:w-full">
                    <button type="button" x-on:click="open($event)" aria-controls="filter-panel" x-bind:aria-expanded="sheet" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-line-strong bg-paper px-4 text-ui font-semibold text-ink hover:border-brand lg:hidden">
                        <x-heroicon-o-adjustments-horizontal class="size-5" aria-hidden="true" />Filter
                        <span id="jumlah-filter" data-swap>@if ($filterCount)<span class="inline-flex size-6 items-center justify-center rounded-full bg-brand text-caption text-paper tabular-nums"><span class="sr-only">aktif: </span>{{ $filterCount }}</span>@endif</span>
                    </button>
                    <div class="min-w-0 flex-1 sm:flex-none">
                        <label for="urut" class="sr-only">Urutkan</label>
                        <x-select id="urut" name="urut" size="sm" :options="DirectorySearch::SORTS" :selected="$search->sort" class="sm:min-w-44" />
                    </div>
                    <div class="flex rounded-full border border-line-strong bg-paper p-0.5" role="group" aria-label="Tampilan hasil">
                        @foreach (['grid' => ['Kartu', 'squares-2x2'], 'list' => ['Daftar', 'list-bullet']] as $mode => [$label, $icon])
                            <button
                                type="button"
                                x-on:click="view = '{{ $mode }}'"
                                x-bind:aria-pressed="view === '{{ $mode }}'"
                                @class(['inline-flex h-10 items-center gap-1.5 rounded-full px-3 text-ui font-semibold transition-colors md:h-9', 'bg-brand text-paper' => $mode === 'grid', 'text-ink-soft hover:text-ink' => $mode !== 'grid'])
                                x-bind:class="{ 'bg-brand text-paper': view === '{{ $mode }}', 'text-ink-soft hover:text-ink': view !== '{{ $mode }}' }"
                            >
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-4" aria-hidden="true" /><span class="max-sm:sr-only">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div id="filter-aktif" data-swap>
                @if ($chips)
                    <ul class="mt-4 flex flex-wrap gap-2" aria-label="Filter aktif">
                        @foreach ($chips as $chip)
                            <li>
                                <a href="{{ $chip['url'] }}" data-directory-link class="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-brand bg-brand-tint py-1 pr-2 pl-3 text-ui font-semibold text-brand transition-colors hover:bg-paper max-md:min-h-11">
                                    {{ $chip['label'] }}<x-heroicon-o-x-mark class="size-4" aria-hidden="true" /><span class="sr-only">: hapus filter</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div id="hasil" data-swap class="mt-6 transition-opacity" x-bind:class="loading && 'opacity-60'" x-bind:aria-busy="loading">
                @if ($organizations->isEmpty())
                    @if ($filterCount || $search->q !== '')
                        <div class="flex flex-col items-center rounded-panel bg-cream px-6 py-14 text-center md:py-20">
                            <span class="flex size-20 items-center justify-center rounded-full bg-paper text-clay" aria-hidden="true">
                                <x-heroicon-o-magnifying-glass class="size-9 stroke-1" />
                            </span>
                            <h2 class="mt-6 font-serif text-display-sm font-semibold text-ink">Tidak ada kelompok yang cocok</h2>
                            <ul class="mt-3 space-y-1 text-body text-ink-soft">
                                @if ($search->q !== '')<li>Periksa ejaan kata pencarian atau pakai kata yang lebih umum.</li>@endif
                                @if ($filterCount)<li>Kurangi jumlah filter yang dipilih.</li>@endif
                            </ul>
                            <x-public.pill-link :href="route('public.directory')" data-directory-link class="mt-8">Hapus semua filter</x-public.pill-link>
                        </div>
                    @else
                        <x-empty-state title="Belum ada kelompok yang dipublikasikan" description="Daftar kelompok akan tampil di sini setelah dipublikasikan oleh Admin distrik." />
                    @endif
                @else
                    <ul data-cards x-reveal.stagger="fade-up" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3 list-view:grid-cols-1! list-view:gap-3">
                        @foreach ($organizations as $organization)
                            <li class="min-w-0">@include('publik.directory._card')</li>
                        @endforeach
                    </ul>

                    @if ($organizations->hasPages())
                        <div class="mt-section-head" data-directory-pages>{{ $organizations->onEachSide(1)->links('publik._pagination') }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</form>
</x-layouts.public>
