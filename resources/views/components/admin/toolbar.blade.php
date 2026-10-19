@props([
    'action',
    // Kotak cari: null = tanpa cari.
    'searchLabel' => null,
    'search' => null,
    'searchName' => 'q',
    // list of ['name' => ..., 'label' => ..., 'options' => [nilai => label], 'selected' => ?string, 'placeholder' => ...]
    'filters' => [],
    // Jumlah filter yang tampil sebaris di tablet/desktop; sisanya di panel "Filter lain".
    'primary' => 3,
    // Parameter yang dipertahankan sebagai input tersembunyi (mis. filter dari tautan dasbor).
    'keep' => [],
    // Pil filter aktif (App\Support\FilterChips::from) + ringkasan hasil.
    'chips' => [],
    'summary' => null,
    'clearUrl' => null,
    // Judul kartu daftar: bilah alat menjadi kepala kartu (judul kiri; cari kecil + tombol Filter di kanan) dan semua
    // filter masuk panel Filter. Tanpa judul (tab Ekonomi/Legalitas): filter utama tetap sebaris.
    'title' => null,
])

@php
    $filters = array_values($filters);
    $primary = $title ? 0 : $primary;
    $inline = array_slice($filters, 0, $primary);
    $others = array_slice($filters, $primary);
    $activeCount = collect($filters)->filter(fn ($filter) => filled($filter['selected'] ?? null))->count();
    $othersActive = collect($others)->filter(fn ($filter) => filled($filter['selected'] ?? null))->count();
    $select = fn (array $filter, string $id, bool $panel) => [
        'name' => $filter['name'],
        'id' => $id,
        'options' => $filter['options'],
        'selected' => $filter['selected'] ?? null,
        'placeholder' => $filter['placeholder'] ?? null,
    ];
@endphp

{{--
    Bilah alat daftar Admin/OPD: cari + maks 3 filter utama sebaris, tombol "Filter lain" membuka panel (drawer kanan di
    desktop, bottom sheet di HP; di HP panel berisi semua filter). Perubahan langsung diterapkan tanpa tombol Terapkan:
    Alpine "adminToolbar" (resources/js/admin-toolbar.js) memuat ulang hasil lewat fetch (cari: jeda 300 ms) dan menukar
    elemen [data-swap]. Tanpa JS tetap form GET biasa. Pil filter aktif bisa dihapus satu per satu, plus "Hapus semua".
--}}
<div x-data="adminToolbar" {{ $attributes->class(['space-y-3', 'px-4 pb-4 pt-4 md:px-5 md:pt-5' => $title]) }}>
    <form novalidate x-ref="form" method="GET" action="{{ $action }}" role="search" x-on:submit.prevent="apply()" x-on:change="apply()" class="flex flex-wrap items-center gap-2">
        @if ($title)
            <h2 class="mr-auto min-h-8 py-1 text-card-head font-semibold text-ink max-sm:w-full">{{ $title }}</h2>
        @endif
        @foreach ($keep as $name => $value)
            @if (filled($value))
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        @if ($searchLabel)
            <x-search-input :name="$searchName" :value="$search" :label="$searchLabel" @class(['min-w-0 flex-1', 'sm:w-60 sm:flex-none' => $title, 'md:max-w-sm' => ! $title]) x-on:input="typed()" x-on:change.stop />
        @endif

        {{-- Filter utama sebaris (mulai tablet). Di HP dinonaktifkan agar tidak terkirim ganda dengan salinan di panel. --}}
        @foreach ($inline as $filter)
            <div class="hidden w-48 md:block">
                <x-select size="sm" :id="$filter['name'].'-inline'" :name="$filter['name']" :options="$filter['options']" :selected="$filter['selected'] ?? null" :placeholder="$filter['placeholder'] ?? null" :aria-label="$filter['label']" x-bind:disabled="! desktop" />
            </div>
        @endforeach

        @if ($filters)
            <x-button variant="secondary" icon="funnel" @class(['md:hidden' => ! $others]) x-on:click="open = true" x-bind:aria-expanded="open" aria-controls="panel-filter">
                @if ($title)
                    Filter{{ $activeCount ? " ({$activeCount})" : '' }}
                @else
                    <span class="md:hidden">Filter{{ $activeCount ? " ({$activeCount})" : '' }}</span>
                    <span class="max-md:hidden">Filter lain{{ $othersActive ? " ({$othersActive})" : '' }}</span>
                @endif
            </x-button>
        @endif

        <span x-cloak x-show="loading" class="text-caption text-ink-muted" role="status">Memuat…</span>
        <noscript><x-button variant="secondary" type="submit">Terapkan filter</x-button></noscript>

        @isset($actions)
            {{-- Aksi yang mengikuti filter (mis. Unduh Excel), rata kanan. --}}
            <div @class(['flex items-center gap-2', 'ml-auto' => ! $title])>{{ $actions }}</div>
        @endisset

        @if ($filters)
            {{-- Panel: drawer kanan (≥ 768px, filter selain yang sebaris) atau bottom sheet (HP, semua filter). --}}
            <div x-cloak x-show="open" class="fixed inset-0 z-40 bg-ink/40" x-on:click="open = false" aria-hidden="true"></div>
            <div
                id="panel-filter"
                x-cloak
                x-show="open"
                x-ref="panel"
                x-on:keydown.escape="open = false"
                x-on:keydown.tab="trap($event)"
                role="dialog"
                aria-modal="true"
                aria-labelledby="panel-filter-judul"
                class="fixed z-50 flex flex-col bg-paper shadow-float max-md:inset-x-0 max-md:bottom-0 max-md:max-h-[85dvh] max-md:rounded-t-box md:inset-y-0 md:right-0 md:w-96"
            >
                <div class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-line px-4">
                    <h2 id="panel-filter-judul" class="text-body font-semibold text-ink">Filter</h2>
                    <button type="button" x-on:click="open = false" class="-mr-2 flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Tutup">
                        <x-heroicon-o-x-mark class="size-5" />
                    </button>
                </div>
                <div class="grid flex-1 content-start gap-4 overflow-y-auto p-4">
                    @foreach ($filters as $index => $filter)
                        {{-- Salinan filter utama hanya untuk HP; nonaktif sejak awal agar form tanpa JS tidak mengirim ganda. --}}
                        <div @class(['md:hidden' => $index < $primary])>
                            <x-field :label="$filter['label']" :for="$filter['name'].'-panel'">
                                <x-select :id="$filter['name'].'-panel'" :name="$filter['name']" :options="$filter['options']" :selected="$filter['selected'] ?? null" :placeholder="$filter['placeholder'] ?? null" :disabled="$index < $primary" x-bind:disabled="{{ $index < $primary ? 'desktop' : 'false' }}" />
                            </x-field>
                        </div>
                    @endforeach
                </div>
                <div class="flex shrink-0 items-center justify-between gap-2 border-t border-line px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                    @if ($clearUrl)
                        <x-button variant="ghost" :href="$clearUrl">Hapus semua</x-button>
                    @else
                        <span></span>
                    @endif
                    <x-button x-on:click="open = false">Selesai</x-button>
                </div>
            </div>
        @endif
    </form>

    <div data-swap="toolbar-summary" class="flex flex-wrap items-center gap-x-3 gap-y-2">
        @if ($summary)
            <p class="text-caption text-ink-2 tabular-nums" role="status">{{ $summary }}</p>
        @endif
        @if ($chips)
            <ul class="flex flex-wrap items-center gap-2" aria-label="Filter aktif">
                @foreach ($chips as $chip)
                    <li>
                        <a href="{{ $chip['href'] }}" class="inline-flex h-11 items-center gap-1 rounded-pill border border-brand-100 bg-brand-50 pl-2.5 pr-1.5 text-label font-medium text-brand transition-colors hover:border-brand md:h-7" aria-label="Hapus filter {{ $chip['label'] }}">
                            {{ $chip['label'] }}
                            <x-heroicon-o-x-mark class="size-4" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
                @if ($clearUrl)
                    <li><x-link :href="$clearUrl" class="text-caption">Hapus semua</x-link></li>
                @endif
            </ul>
        @endif
    </div>
</div>
