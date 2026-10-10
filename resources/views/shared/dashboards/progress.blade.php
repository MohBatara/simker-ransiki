{{-- Kartu progres pelaporan satu periode. Variabel: $progress, $period, $area, $extra (opsional). --}}
@use('App\Support\Format')
@use('App\Support\Dashboards\ReportingStats')

@php
    $submitted = $progress['verified'] + $progress['submitted'] + $progress['revision'];
@endphp

<x-card :title="'Pelaporan '.Format::month($period)">
    @if ($progress['total'] === 0)
        <p class="text-ink-soft">Belum ada kelompok yang wajib melapor untuk bulan ini.</p>
    @else
        <p class="mb-4 text-ink-soft">
            <span class="text-card-title font-semibold tabular-nums text-ink">{{ $submitted }} dari {{ $progress['total'] }}</span>
            kelompok wajib lapor sudah mengajukan laporan.
        </p>
        <x-stacked-bar :label="'Pelaporan '.Format::month($period)" :segments="ReportingStats::segments($progress, $area, $period, $extra ?? [])" />
    @endif
</x-card>
