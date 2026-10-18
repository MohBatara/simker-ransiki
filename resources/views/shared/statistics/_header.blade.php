{{--
    Bilah alat tab Ekonomi/Legalitas (x-admin.toolbar): rentang bulan (Ekonomi saja), kampung, jenis, bidang usaha, OPD
    pembina (Admin saja; untuk OPD parameter itu juga diabaikan di server, Statistics::__construct). Langsung diterapkan.
    Variabel: $area, $tab ('economy'|'legal'), $filters, $options, $stats, $months (null di Legalitas), $summaryText.
--}}
@php
    $url = route($area.'.statistics.'.$tab);
    $chips = \App\Support\FilterChips::from($filters, [
        'village' => ['Kampung', $options['villages']],
        'type' => ['Jenis', $options['types']],
        'sector' => ['Bidang', $options['sectors']],
        'opd' => ['OPD', ['none' => 'Belum ada OPD'] + $options['opds']],
    ]);
    $toolbarFilters = array_values(array_filter([
        $months ? ['name' => 'dari', 'label' => 'Dari bulan', 'options' => array_reverse($months, true), 'selected' => $stats->from()->format('Y-m')] : null,
        $months ? ['name' => 'sampai', 'label' => 'Sampai bulan', 'options' => $months, 'selected' => $stats->to()->format('Y-m')] : null,
        ['name' => 'village', 'label' => 'Kampung', 'options' => $options['villages'], 'selected' => $filters['village'] ?? null, 'placeholder' => 'Semua kampung'],
        ['name' => 'type', 'label' => 'Jenis', 'options' => $options['types'], 'selected' => $filters['type'] ?? null, 'placeholder' => 'Semua jenis'],
        ['name' => 'sector', 'label' => 'Bidang usaha', 'options' => $options['sectors'], 'selected' => $filters['sector'] ?? null, 'placeholder' => 'Semua bidang'],
        $area === 'admin' ? ['name' => 'opd', 'label' => 'OPD pembina', 'options' => ['none' => 'Belum ada OPD'] + $options['opds'], 'selected' => $filters['opd'] ?? null, 'placeholder' => 'Semua OPD'] : null,
    ]));
@endphp

<x-admin.toolbar :action="$url" :filters="$toolbarFilters" :chips="$chips" :summary="$summaryText" :clear-url="$chips ? $url : null" aria-label="Filter statistik">
    @if ($tab === 'legal')
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-down-tray" :href="route($area.'.statistics.legal.export', $filters)" data-swap="unduh-legalitas">Unduh Excel</x-button>
        </x-slot:actions>
    @endif
</x-admin.toolbar>
