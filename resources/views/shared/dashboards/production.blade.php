{{--
    Kartu "Produksi per produk" dasbor Ringkasan (selebar penuh): 5 produk teratas dari laporan terverifikasi, produk +
    satuan (satuan berbeda tidak dijumlahkan). Variabel: $production, $period, $area.
--}}
@use('App\Support\Format')

<x-admin.card :title="'Produksi per produk, '.Format::month($period)" description="Lima produk teratas. Satuan berbeda tidak dijumlahkan." flush :more="route($area.'.statistics.economy')" more-label="Lihat semua di tab Ekonomi">
    @if ($production->isEmpty())
        <p class="border-t border-line px-4 py-5 text-caption text-ink-2 md:px-5">Belum ada produksi yang dilaporkan.</p>
    @else
        <x-admin.table bare>
            <x-slot:head>
                <x-admin.table.heading>Produk</x-admin.table.heading>
                <x-admin.table.heading numeric>Produksi</x-admin.table.heading>
                <x-admin.table.heading numeric>Kelompok</x-admin.table.heading>
            </x-slot:head>
            @foreach ($production->take(5) as $row)
                <x-admin.table.row>
                    <x-admin.table.cell primary>{{ $row['product'] }}</x-admin.table.cell>
                    <x-admin.table.cell label="Produksi" numeric>
                        @if ($row['quantity'] === null)
                            <span class="text-ink-3">Tidak diisi</span>
                        @else
                            {{ \App\Support\Format::orEmpty(Format::quantity($row['quantity'])) }} {{ $row['unit'] }}
                            @if ($row['filled'] < $row['organizations'])
                                <span class="block text-label text-ink-3">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                            @endif
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell label="Kelompok" numeric>{{ \App\Support\Format::orEmpty(Format::number($row['organizations'])) }}</x-admin.table.cell>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>
    @endif
</x-admin.card>
