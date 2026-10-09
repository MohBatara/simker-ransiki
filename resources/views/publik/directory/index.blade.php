@use('App\Support\Format')

@php
    $activeFilters = collect($filters)->except('q')->filter()->count();
    $hasFilters = $activeFilters > 0 || $filters['q'] !== '';
@endphp

<x-layouts.public title="Direktori kelompok" description="Cari kelompok dan kelembagaan ekonomi Distrik Ransiki berdasarkan nama, produk, jenis, bidang usaha, atau kampung.">
    <h1 class="text-display-sm font-semibold text-ink md:text-display">Direktori kelompok</h1>
    <p class="mt-2 text-body text-ink-soft tabular-nums">{{ Format::number($organizations->total()) }} kelompok{{ $hasFilters ? ' cocok dengan pencarian' : ' terdaftar' }}</p>

    <form method="GET" action="{{ route('public.directory') }}" role="search" x-data="{ open: @js($activeFilters > 0) }" class="mt-6 space-y-3">
        <div class="flex flex-wrap gap-2">
            <label for="q" class="sr-only">Cari nama kelompok atau produk</label>
            <x-input name="q" :value="$filters['q']" placeholder="Cari nama kelompok atau produk" class="min-w-0 flex-1 md:max-w-md" />
            <x-button variant="secondary" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="filter-direktori">
                Filter{{ $activeFilters ? " ({$activeFilters})" : '' }}
            </x-button>
            <x-button type="submit">Cari</x-button>
        </div>
        {{-- Filter langsung diterapkan saat pilihan berubah. --}}
        <div id="filter-direktori" x-bind:class="{ 'max-md:hidden': ! open }" @class(['grid gap-3 md:grid-cols-3 lg:max-w-3xl', 'max-md:hidden' => ! $activeFilters])>
            <x-select name="jenis" :options="$options['jenis']" :selected="$filters['jenis']" placeholder="Semua jenis" aria-label="Jenis kelembagaan" x-on:change="$el.form.requestSubmit()" />
            <x-select name="bidang" :options="$options['bidang']" :selected="$filters['bidang']" placeholder="Semua bidang usaha" aria-label="Bidang usaha" x-on:change="$el.form.requestSubmit()" />
            <x-select name="kampung" :options="$options['kampung']" :selected="$filters['kampung']" placeholder="Semua kampung" aria-label="Kampung" x-on:change="$el.form.requestSubmit()" />
        </div>
        @if ($hasFilters)
            <a href="{{ route('public.directory') }}" class="inline-block rounded-control text-ui text-brand hover:text-brand-hover">Hapus pencarian dan filter</a>
        @endif
    </form>

    @if ($organizations->isEmpty())
        <div class="mt-8">
            <x-empty-state
                :title="$hasFilters ? 'Tidak ada kelompok yang cocok' : 'Belum ada kelompok yang dipublikasikan'"
                :description="$hasFilters ? 'Ubah kata pencarian atau hapus filter.' : 'Daftar kelompok akan tampil di sini setelah dipublikasikan oleh Admin distrik.'"
            />
        </div>
    @else
        <ul class="mt-8 divide-y divide-line border-y border-line">
            @foreach ($organizations as $organization)
                <li class="flex flex-col gap-4 py-5 md:flex-row md:items-start md:justify-between md:gap-8">
                    <div class="min-w-0">
                        <h2 class="text-card-title font-semibold">
                            <a href="{{ route('public.organization', $organization->slug) }}" class="rounded-control text-ink hover:text-brand">{{ $organization->name }}</a>
                        </h2>
                        <p class="mt-0.5 text-caption text-ink-muted">{{ $organization->institutionType->name }} · {{ $organization->village->name }}</p>
                        <p class="mt-1 text-ui text-ink-soft">{{ $organization->sectors->pluck('name')->join(' · ') }}</p>
                        @if ($organization->description)
                            <p class="mt-2 line-clamp-2 max-w-[42rem] text-ui text-ink">{{ $organization->description }}</p>
                        @endif
                        @if ($organization->products->isNotEmpty())
                            <p class="mt-2 text-caption text-ink-soft">Produk: {{ $organization->products->pluck('name')->map(fn ($name) => lcfirst($name))->join(', ') }}</p>
                        @endif
                    </div>
                    @php $photos = $organization->products->whereNotNull('photo_path')->take(3); @endphp
                    @if ($photos->isNotEmpty())
                        <div class="flex shrink-0 gap-2" aria-hidden="true">
                            @foreach ($photos as $product)
                                <img src="{{ $product->photoUrl(thumbnail: true) }}" alt="" width="64" height="64" loading="lazy" decoding="async" class="size-16 rounded-control border border-line object-cover">
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $organizations->links() }}</div>
    @endif
</x-layouts.public>
