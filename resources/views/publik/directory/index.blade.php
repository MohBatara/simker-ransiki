@use('App\Support\Format')
@use('App\Support\PublicIcons')

@php
    $activeFilters = collect($filters)->except('q')->filter()->count();
    $hasFilters = $activeFilters > 0 || $filters['q'] !== '';
@endphp

<x-layouts.public title="Direktori kelompok" description="Cari kelompok dan kelembagaan ekonomi Distrik Ransiki berdasarkan nama, produk, jenis, bidang usaha, atau kampung.">
    <x-public.band title="Direktori kelompok" :crumbs="['Direktori' => null]" :lead="Format::number($organizations->total()).' kelompok'.($hasFilters ? ' cocok dengan pencarian' : ' terdaftar')">
        <form method="GET" action="{{ route('public.directory') }}" role="search" id="cari-direktori" class="mt-6 flex max-w-2xl gap-2">
            <label for="q" class="sr-only">Cari nama kelompok atau produk</label>
            <x-input name="q" :value="$filters['q']" placeholder="Cari nama kelompok atau produk" class="min-w-0 flex-1 md:h-12!" />
            <x-public.button type="submit" icon="magnifying-glass">Cari</x-public.button>
        </form>
    </x-public.band>

    <div class="mx-auto max-w-public px-4 py-section md:px-6">
        {{-- Filter langsung diterapkan saat pilihan berubah; ikut mengirim kata pencarian dari pita judul. --}}
        <div x-data="{ open: @js($activeFilters > 0) }" class="space-y-3">
            {{-- Tanpa pencarian/filter aktif, baris ini kosong di desktop: disembunyikan agar tidak menambah jarak. --}}
            <div @class(['flex flex-wrap items-center gap-2', 'md:hidden' => ! $hasFilters])>
                <x-button variant="secondary" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="filter-direktori">
                    Filter{{ $activeFilters ? " ({$activeFilters})" : '' }}
                </x-button>
                @if ($hasFilters)
                    <a href="{{ route('public.directory') }}" class="rounded-control text-ui font-semibold text-brand hover:text-brand-hover">Hapus pencarian dan filter</a>
                @endif
            </div>
            <div id="filter-direktori" x-bind:class="{ 'max-md:hidden': ! open }" @class(['grid gap-3 md:grid-cols-3 lg:max-w-3xl', 'max-md:hidden' => ! $activeFilters])>
                <x-select name="jenis" form="cari-direktori" :options="$options['jenis']" :selected="$filters['jenis']" placeholder="Semua jenis" aria-label="Jenis kelembagaan" x-on:change="$el.form.requestSubmit()" />
                <x-select name="bidang" form="cari-direktori" :options="$options['bidang']" :selected="$filters['bidang']" placeholder="Semua bidang usaha" aria-label="Bidang usaha" x-on:change="$el.form.requestSubmit()" />
                <x-select name="kampung" form="cari-direktori" :options="$options['kampung']" :selected="$filters['kampung']" placeholder="Semua kampung" aria-label="Kampung" x-on:change="$el.form.requestSubmit()" />
            </div>
        </div>

        @if ($organizations->isEmpty())
            <div class="mt-section-head">
                <x-empty-state
                    :title="$hasFilters ? 'Tidak ada kelompok yang cocok' : 'Belum ada kelompok yang dipublikasikan'"
                    :description="$hasFilters ? 'Ubah kata pencarian atau hapus filter.' : 'Daftar kelompok akan tampil di sini setelah dipublikasikan oleh Admin distrik.'"
                />
            </div>
        @else
            <div class="mt-section-head grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($organizations as $organization)
                    @php
                        $url = route('public.organization', $organization->slug);
                        $photo = $organization->products->firstWhere('photo_path', '!=', null);
                        $initials = collect(preg_split('/\s+/', $organization->name))->filter(fn ($word) => ! preg_match('/^\d+$/', $word))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                    @endphp
                    <article class="flex flex-col overflow-hidden rounded-public border border-line bg-paper shadow-card">
                        <div class="relative">
                        <a href="{{ $url }}" class="block aspect-[16/10] bg-subtle" tabindex="-1" aria-hidden="true">
                            @if ($photo)
                                <x-public.picture :image="$photo->photoSources()" alt="" sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 50vw, 100vw" width="400" height="250" frame="block size-full" class="size-full object-cover" />
                            @else
                                {{-- Tanpa foto: inisial kelompok di latar subtle. --}}
                                <span class="flex size-full items-center justify-center text-display font-semibold text-ink-muted">{{ $initials }}</span>
                            @endif
                        </a>
                        <x-public.icon-badge :icon="PublicIcons::institutionType($organization->institutionType->name)" class="absolute -bottom-6 left-4" />
                        </div>
                        <div class="flex flex-1 flex-col items-start gap-1.5 px-4 pt-8 pb-4">
                            <div class="flex flex-wrap gap-2">
                                <x-public.pill>{{ $organization->institutionType->name }}</x-public.pill>
                                @if ($organization->profile_status === App\Enums\ProfileStatus::Verified)
                                    <x-public.pill>Profil diverifikasi OPD</x-public.pill>
                                @endif
                                @if ($organization->status === App\Enums\OrganizationStatus::Inactive)
                                    <x-public.pill>{{ $organization->status->label() }}</x-public.pill>
                                @endif
                            </div>
                            <h2 class="font-serif text-card-title font-semibold">
                                <a href="{{ $url }}" class="rounded-control text-ink hover:text-brand">{{ $organization->name }}</a>
                            </h2>
                            <p class="text-caption text-ink-muted">{{ $organization->village->name }} · {{ $organization->sectors->pluck('name')->join(', ') }}</p>
                            @if ($organization->description)
                                <p class="line-clamp-2 text-ui text-ink">{{ $organization->description }}</p>
                            @endif
                            @if ($organization->products->isNotEmpty())
                                <p class="text-caption text-ink-soft">Produk: {{ $organization->products->pluck('name')->map(fn ($name) => lcfirst($name))->join(', ') }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($organizations->hasPages())
                <div class="mt-8">{{ $organizations->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts.public>
