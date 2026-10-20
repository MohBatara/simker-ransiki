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

        {{-- Grafik 5 kolom + Produksi per produk 7 kolom (seperti dasbor): tabel empat kolom muat tanpa digeser. --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
            <x-chart
                title="Pendapatan per bidang usaha"
                :chart="StatisticsCharts::incomeBySector($sectors)"
                note="Kelompok dengan lebih dari satu bidang usaha dihitung di setiap bidangnya, sehingga angka antarbidang tidak boleh dijumlahkan."
                class="lg:col-span-5"
            />

            <x-admin.card title="Produksi per produk" description="Produk yang paling banyak diproduksi kelompok, dari laporan terverifikasi. Satuan berbeda tidak dijumlahkan." flush class="lg:col-span-7">
                @if ($production->isEmpty())
                    <p class="px-4 py-4 text-ink-soft md:px-5">Belum ada laporan terverifikasi yang mengisi produksi pada rentang ini.</p>
                @else
                    <x-admin.table bare class="max-md:p-4" label="Tabel produksi per produk">
                        <x-slot:head>
                            <x-admin.table.heading>Produk</x-admin.table.heading>
                            <x-admin.table.heading numeric fit>Jumlah</x-admin.table.heading>
                            <x-admin.table.heading numeric>Kelompok</x-admin.table.heading>
                            {{-- Rentang sebelumnya sama panjang (satu bulan = bulan lalu); periodenya di tooltip tiap angka. --}}
                            <x-admin.table.heading numeric fit wrap>Dibanding periode sebelumnya</x-admin.table.heading>
                        </x-slot:head>
                        @foreach ($production as $row)
                            <x-admin.table.row>
                                <x-admin.table.cell primary>{{ $row['product'] }}</x-admin.table.cell>
                                {{-- Satuan menempel pada jumlah ("1.384 ekor") di semua ukuran layar, agar tabel muat di kartu setengah lebar. --}}
                                <x-admin.table.cell label="Jumlah" numeric fit inline>
                                    @if ($row['quantity'] === null)
                                        <span class="text-ink-3">Tidak diisi</span>
                                    @else
                                        {{ Format::quantity($row['quantity']) }} {{ $row['unit'] ?? 'tanpa satuan' }}
                                        @if ($row['filled'] < $row['organizations'])
                                            <span class="block text-label text-ink-3">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                                        @endif
                                    @endif
                                </x-admin.table.cell>
                                <x-admin.table.cell label="Kelompok" numeric inline>@include('shared.dashboards._production-cells', ['cell' => 'organizations'])</x-admin.table.cell>
                                <x-admin.table.cell label="Dibanding periode sebelumnya" numeric fit inline>@include('shared.dashboards._production-cells', ['cell' => 'change'])</x-admin.table.cell>
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
