{{--
    Grafik ringkas tab Ringkasan (Admin/OPD), tanpa filter: tren pendapatan 12 bulan (bulan sementara putus-putus) dan
    kepatuhan pelaporan 6 bulan. Data dari App\Support\Dashboards\Statistics::overview, sama dengan tab Ekonomi.
    Berdampingan mulai 1024px, bertumpuk di HP. Variabel: $overview, $area.
--}}
@use('App\Support\Dashboards\StatisticsCharts')

@php
    $more = ['href' => route($area.'.statistics.economy'), 'label' => 'Lihat statistik lengkap'];
@endphp

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:items-stretch">
    <x-chart
        title="Tren pendapatan, 12 bulan"
        :chart="StatisticsCharts::incomeTrend($overview['incomeTrend'])"
        :height="220"
        :more="$more"
    />
    <x-chart
        title="Kepatuhan pelaporan, 6 bulan"
        source="Status laporan kelompok wajib lapor per bulan."
        :chart="StatisticsCharts::compliance($overview['compliance'])"
        :height="220"
        :more="$more"
    />
</div>
