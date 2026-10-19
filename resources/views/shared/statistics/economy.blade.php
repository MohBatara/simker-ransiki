{{--
    Dasbor → Ekonomi (Admin dan OPD). Semua angka dari App\Support\Dashboards\Statistics, dibatasi
    kelompok yang terlihat pengguna. Angka ekonomi hanya dari laporan terverifikasi.
--}}
@use('App\Support\Format')
@use('App\Support\Dashboards\StatisticsCharts')

@php
    $range = Format::month($stats->from()).' sampai '.Format::month($stats->to());
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

        <div class="grid grid-cols-2 gap-4 xl:grid-cols-3">
            <x-admin.stat compact icon="user-group" label="Kelompok" :value="Format::number($summary['organizations'])" hint="sesuai filter" />
            <x-admin.stat compact icon="document-check" label="Laporan terverifikasi" :value="Format::number($summary['reports'])" :hint="'dari '.$summary['reporting_organizations'].' kelompok'" />
            <x-admin.stat compact icon="banknotes" label="Pendapatan penjualan" :value="Format::rupiah($summary['sales'])" :hint="$salesHint" class="max-xl:col-span-2" />
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <x-chart
                title="Pendapatan per bidang usaha"
                :chart="StatisticsCharts::incomeBySector($sectors)"
                note="Kelompok dengan lebih dari satu bidang usaha dihitung di setiap bidangnya, sehingga angka antarbidang tidak boleh dijumlahkan."
            />

            <x-admin.card title="Produksi per produk" description="Dari laporan terverifikasi. Satuan berbeda tidak dijumlahkan." flush>
                @if ($production->isEmpty())
                    <p class="px-4 py-4 text-ink-soft md:px-5">Belum ada laporan terverifikasi yang mengisi produksi pada rentang ini.</p>
                @else
                    <x-admin.table bare class="max-md:p-4">
                        <x-slot:head>
                            <x-admin.table.heading>Produk</x-admin.table.heading>
                            <x-admin.table.heading numeric>Jumlah</x-admin.table.heading>
                            <x-admin.table.heading>Satuan</x-admin.table.heading>
                            <x-admin.table.heading numeric>Kelompok</x-admin.table.heading>
                        </x-slot:head>
                        @foreach ($production as $row)
                            <x-admin.table.row>
                                <x-admin.table.cell primary>{{ $row['product'] }}</x-admin.table.cell>
                                {{-- HP: satuan ikut di baris Jumlah ("Jumlah: 1.384 ekor"), kolom Satuan disembunyikan. --}}
                                <x-admin.table.cell label="Jumlah" numeric inline>{{ Format::quantity($row['quantity']) }}<span class="md:hidden"> {{ $row['unit'] ?? 'tanpa satuan' }}</span></x-admin.table.cell>
                                <x-admin.table.cell label="Satuan" class="max-md:hidden">{{ $row['unit'] ?? 'Tanpa satuan' }}</x-admin.table.cell>
                                <x-admin.table.cell label="Kelompok" numeric inline>{{ Format::number($row['organizations']) }}</x-admin.table.cell>
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                @endif
            </x-admin.card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <x-chart title="Masalah terbanyak" :chart="StatisticsCharts::issues($issues['problem'], 'Masalah')" note="Jumlah laporan yang menyebut kategori ini." />
            <x-chart title="Kebutuhan dukungan terbanyak" :chart="StatisticsCharts::issues($issues['support'], 'Kebutuhan dukungan')" note="Jumlah laporan yang menyebut kategori ini." />
        </div>
        </div>
    </div>
</x-layouts.app>
