{{-- Filter + ringkasan filter aktif (tab Ekonomi/Legalitas ada di layout). Variabel: $area, $tab, $filters, $options, $summaryText. --}}
@php
    $chips = \App\Support\FilterChips::from($filters, [
        'village' => ['Kampung', $options['villages']],
        'type' => ['Jenis', $options['types']],
        'sector' => ['Bidang', $options['sectors']],
        'opd' => ['OPD', ['none' => 'Belum ada OPD'] + $options['opds']],
    ]);
@endphp

@include('shared.statistics._filters', ['url' => route($area.'.statistics.'.$tab)])
<x-filter-summary :summary="$summaryText" :chips="$chips" :clear-url="$chips ? route($area.'.statistics.'.$tab) : null" />
