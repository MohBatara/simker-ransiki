{{-- Tab Ekonomi/Legalitas + ringkasan filter aktif. Variabel: $area, $tab, $filters, $options, $summaryText. --}}
@php
    $chips = \App\Support\FilterChips::from($filters, [
        'village' => ['Kampung', $options['villages']],
        'type' => ['Jenis', $options['types']],
        'sector' => ['Bidang', $options['sectors']],
        'opd' => ['OPD', ['none' => 'Belum ada OPD'] + $options['opds']],
    ]);
@endphp

<x-tabs
    :tabs="['economy' => 'Ekonomi', 'legal' => 'Legalitas']"
    :active="$tab"
    :links="['economy' => route($area.'.statistics.economy', $filters), 'legal' => route($area.'.statistics.legal', $filters)]"
/>
@include('shared.statistics._filters', ['url' => route($area.'.statistics.'.$tab)])
<x-filter-summary :summary="$summaryText" :chips="$chips" :clear-url="$chips ? route($area.'.statistics.'.$tab) : null" />
