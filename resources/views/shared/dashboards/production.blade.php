{{--
    Kartu "Produksi per produk" dasbor Ringkasan (7 dari 12 kolom, berdampingan dengan Belum mengajukan): 5 produk dari
    laporan terverifikasi (Statistics::production), urut jumlah kelompok yang memproduksi lalu nama produk; produk + satuan
    (satuan berbeda tidak dijumlahkan), kelompok, dan perubahan setara dibanding bulan lalu.
    Variabel: $production, $period, $area, $class (opsional).
--}}
@use('App\Support\Format')

@php
    // Judul kolom pembanding satu baris: "Vs Agustus" (nama bulan sebelum periode terpilih).
    $versus = 'Vs '.\Carbon\CarbonImmutable::parse($period)->subMonthNoOverflow()->translatedFormat('F');
@endphp

<x-admin.card :title="'Produksi per produk, '.Format::month($period)" description="Lima produk yang paling banyak diproduksi kelompok. Satuan berbeda tidak dijumlahkan." flush :more="route($area.'.statistics.economy')" more-label="Lihat semua di tab Ekonomi" stretch :class="$class ?? null">
    @if ($production->isEmpty())
        <p class="border-t border-line px-4 py-5 text-caption text-ink-2 md:px-5">Belum ada produksi yang dilaporkan.</p>
    @else
        <x-admin.table bare>
            <x-slot:head>
                <x-admin.table.heading>Produk</x-admin.table.heading>
                <x-admin.table.heading numeric fit>Produksi</x-admin.table.heading>
                <x-admin.table.heading>Kelompok</x-admin.table.heading>
                <x-admin.table.heading numeric fit>{{ $versus }}</x-admin.table.heading>
            </x-slot:head>
            @foreach ($production->take(5) as $row)
                <x-admin.table.row>
                    <x-admin.table.cell primary>{{ $row['product'] }}</x-admin.table.cell>
                    <x-admin.table.cell label="Produksi" numeric fit>
                        @if ($row['quantity'] === null)
                            <span class="text-ink-3">Tidak diisi</span>
                        @else
                            {{ Format::quantity($row['quantity']) }} {{ $row['unit'] ?? 'tanpa satuan' }}
                            @if ($row['filled'] < $row['organizations'])
                                <span class="block text-label text-ink-3">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                            @endif
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell label="Kelompok">@include('shared.dashboards._production-cells', ['cell' => 'organizations'])</x-admin.table.cell>
                    <x-admin.table.cell :label="$versus" numeric fit>@include('shared.dashboards._production-cells', ['cell' => 'change'])</x-admin.table.cell>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>
    @endif
</x-admin.card>
