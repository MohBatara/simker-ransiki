{{--
    Kartu filter Statistik: dipilih dulu, lalu Terapkan. Rentang bulan hanya di tab Ekonomi ($months).
    Pilihan OPD hanya untuk Admin; untuk OPD parameter itu juga diabaikan di server (Statistics::__construct).
    Variabel: $area, $filters, $options, $stats, $months (null di tab Legalitas), $url.
--}}
@php
    $isAdmin = $area === 'admin';
    $active = count($filters);
@endphp

<x-card flush x-data="{ open: false }" class="max-md:border-0 max-md:bg-transparent">
    <form method="GET" action="{{ $url }}" role="search" aria-label="Filter statistik" class="grid gap-4 md:grid-cols-4 md:items-end md:p-5">
        <x-button variant="secondary" icon="adjustments-horizontal" class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="panel-filter-statistik">
            Filter{{ $active ? " ({$active})" : '' }}
        </x-button>
        <div id="panel-filter-statistik" x-bind:class="{ 'max-md:hidden': ! open }" class="grid gap-4 max-md:hidden sm:grid-cols-2 md:contents">
            @if ($months)
                <x-field label="Dari bulan" for="dari">
                    <x-select name="dari" :options="array_reverse($months, true)" :selected="$stats->from()->format('Y-m')" />
                </x-field>
                <x-field label="Sampai bulan" for="sampai">
                    <x-select name="sampai" :options="$months" :selected="$stats->to()->format('Y-m')" />
                </x-field>
            @endif
            <x-field label="Kampung" for="village">
                <x-select name="village" :options="$options['villages']" :selected="$filters['village'] ?? null" placeholder="Semua kampung" />
            </x-field>
            <x-field label="Jenis" for="type">
                <x-select name="type" :options="$options['types']" :selected="$filters['type'] ?? null" placeholder="Semua jenis" />
            </x-field>
            <x-field label="Bidang usaha" for="sector">
                <x-select name="sector" :options="$options['sectors']" :selected="$filters['sector'] ?? null" placeholder="Semua bidang" />
            </x-field>
            @if ($isAdmin)
                <x-field label="OPD pembina" for="opd">
                    <x-select name="opd" :options="['none' => 'Belum ada OPD'] + $options['opds']" :selected="$filters['opd'] ?? null" placeholder="Semua OPD" />
                </x-field>
            @endif
            <div @class([
                'flex gap-2 max-sm:*:flex-1 sm:col-span-2 md:justify-end',
                'md:col-span-2' => $months && $isAdmin,
                'md:col-span-3' => $months && ! $isAdmin,
                'md:col-span-4' => ! $months && $isAdmin,
                'md:col-span-1' => ! $months && ! $isAdmin,
            ])>
                <x-button type="submit">Terapkan</x-button>
                <x-button variant="secondary" icon="arrow-path" :href="$url">Reset</x-button>
            </div>
        </div>
    </form>
</x-card>
