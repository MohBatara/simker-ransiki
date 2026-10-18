{{--
    Empat kartu angka pelaporan periode terpilih (dari ReportingStats::progress dan ::overdue, tanpa query baru).
    Variabel: $progress, $overdue, $period, $area.
--}}
@use('App\Support\Format')
@use('App\Support\Dashboards\ReportingPeriod')

@php
    $month = $period->format('Y-m');
    $submitted = $progress['verified'] + $progress['submitted'] + $progress['revision'];
    $of = 'dari '.Format::number($progress['total']).' kelompok wajib lapor';
@endphp

<div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
    <x-admin.stat icon="user-group" :label="'Wajib lapor '.Format::month($period)" :value="Format::number($progress['total'])" hint="kelompok aktif dan tidak aktif sementara" :href="route($area.'.organizations.index')" />
    <x-admin.stat icon="paper-airplane" label="Sudah mengajukan" :value="Format::number($submitted)" :hint="$of" :href="route($area.'.reports.index', ['period' => $month])" />
    <x-admin.stat icon="check-badge" label="Terverifikasi" :value="Format::number($progress['verified'])" :hint="$of" :href="route($area.'.reports.index', ['status' => 'verified', 'period' => $month])" />
    <x-admin.stat
        icon="clock"
        label="Terlambat melapor"
        :value="Format::number($overdue['total'])"
        :tone="$overdue['total'] > 0 ? 'danger' : null"
        :hint="'Tenggat '.Format::date($overdue['deadline']).', '.ReportingPeriod::deadlineDistance($overdue['deadline'])"
        :href="route($area.'.organizations.index', ['laporan' => 'terlambat', 'periode' => $month])"
    />
</div>
