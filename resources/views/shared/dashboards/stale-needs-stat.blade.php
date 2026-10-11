{{-- Kartu ringkasan "Kebutuhan belum ditindaklanjuti" (status Baru/Ditinjau > N hari). Variabel: $staleNeeds, $area. --}}
@use('App\Support\Format')

@php
    $days = config('simker.follow_up.stale_days');
@endphp

<x-stat
    icon="inbox-stack"
    label="Kebutuhan belum ditindaklanjuti"
    :value="Format::number($staleNeeds)"
    :tone="$staleNeeds > 0 ? 'wait' : null"
    :hint="'Baru atau ditinjau, lebih dari '.$days.' hari'"
    :href="route($area.'.issues.index', ['kind' => 'support', 'lama' => $days])"
/>
