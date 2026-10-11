{{-- Kartu ringkasan "Terlambat" periode terpilih. Variabel: $overdue (ReportingStats::overdue), $period, $area. --}}
@use('App\Support\Format')
@use('App\Support\Dashboards\ReportingPeriod')

<x-stat
    icon="clock"
    :label="'Terlambat melapor '.Format::month($period)"
    :value="Format::number($overdue['total'])"
    :tone="$overdue['total'] > 0 ? 'fix' : null"
    :hint="'Tenggat '.Format::date($overdue['deadline']).' · '.ReportingPeriod::deadlineDistance($overdue['deadline'])"
    :href="route($area.'.organizations.index', ['laporan' => 'terlambat', 'periode' => $period->format('Y-m')])"
/>
