{{--
    Dasbor → Ekonomi (Admin dan OPD). Semua angka dari App\Support\Dashboards\Statistics, dibatasi
    kelompok yang terlihat pengguna. Angka ekonomi hanya dari laporan terverifikasi.
--}}
@use('App\Support\Format')
@use('App\Support\Dashboards\StatisticsCharts')

@php
    $range = Format::month($stats->from()).' sampai '.Format::month($stats->to());
    $lastMonth = $incomeTrend->last();
    $lastHint = $lastMonth['total'] === null ? 'belum ada yang mengisi' : 'diisi '.$lastMonth['filled'].' dari '.$lastMonth['obligated'].' kelompok';
    $salesHint = $summary['sales'] === null
        ? 'belum ada yang mengisi'
        : 'diisi '.$summary['sales_filled'].' dari '.$summary['reports'].' laporan';
@endphp

<x-layouts.app
    title="Ekonomi"
    :description="$area === 'opd' ? 'Pendapatan, produksi, dan kepatuhan pelaporan kelompok binaan.' : 'Pendapatan, produksi, dan kepatuhan pelaporan kelompok di Distrik Ransiki.'"
    section="dasbor"
    tab="ekonomi"
    :tab-urls="['ekonomi' => route($area.'.statistics.economy', $filters), 'legalitas' => route($area.'.statistics.legal', $filters)]"
>
    <div class="space-y-6">
        @include('shared.statistics._header', ['tab' => 'economy', 'summaryText' => $range])

        {{-- Ditukar oleh x-admin.toolbar saat filter berubah. --}}
        <div data-swap="statistik" class="space-y-6">

        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-admin.stat compact icon="user-group" label="Kelompok" :value="Format::number($summary['organizations'])" hint="sesuai filter" />
            <x-admin.stat compact icon="document-check" label="Laporan terverifikasi" :value="Format::number($summary['reports'])" :hint="'dari '.$summary['reporting_organizations'].' kelompok'" />
            <x-admin.stat compact icon="banknotes" label="Pendapatan penjualan" :value="Format::rupiah($summary['sales'])" :hint="$salesHint" />
            <x-admin.stat compact icon="arrow-trending-up" :label="'Penjualan '.Format::month($stats->to())" :value="Format::rupiah($lastMonth['total'])" :hint="$lastHint" :change="$salesChange" />
        </div>

        <x-chart
            title="Tren pendapatan penjualan"
            :chart="StatisticsCharts::incomeTrend($incomeTrend)"
            note="Bulan tanpa isian tidak digambar (garis terputus), bukan dianggap 0. Cakupan per bulan ada di keterangan titik dan di tabel."
        />

        <x-chart
            title="Kepatuhan pelaporan"
            source="Status laporan kelompok wajib lapor per bulan."
            :chart="StatisticsCharts::compliance($compliance)"
            note="Terlambat: lewat tenggat dan belum pernah diajukan. Belum diajukan: belum dibuat atau masih draf, tenggat belum lewat."
        />

        {{-- Baris atas: tiga kartu ringkasan sama tinggi. Catatan cara menghitung di ikon info (i). --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-stretch">
            <x-chart
                title="Pendapatan per bidang usaha"
                :chart="StatisticsCharts::incomeBySector($sectors)"
                :info="['Kelompok dengan lebih dari satu bidang usaha dihitung di setiap bidangnya, sehingga angka antarbidang tidak boleh dijumlahkan.']"
            />
            <x-chart title="Masalah terbanyak" :chart="StatisticsCharts::issues($issues['problem'], 'Masalah')" :info="['Jumlah laporan yang menyebut kategori ini.']" />
            <x-chart title="Kebutuhan dukungan terbanyak" :chart="StatisticsCharts::issues($issues['support'], 'Kebutuhan dukungan')" :info="['Jumlah laporan yang menyebut kategori ini.']" />
        </div>

        {{-- Produksi per produk selebar penuh, lengkap (urutan sama dengan Dasbor). --}}
        @include('shared.statistics._production', ['production' => $production])
        </div>
    </div>
</x-layouts.app>
