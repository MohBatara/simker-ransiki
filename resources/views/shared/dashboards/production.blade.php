{{--
    Kartu "Produksi per produk" dasbor Admin (selebar penuh): 5 produk teratas dari laporan terverifikasi, produk + satuan
    (satuan berbeda tidak dijumlahkan). Variabel: $production (DistrictStats::production()), $period.
--}}
@use('App\Support\Format')

<x-admin.card :title="'Produksi per produk, '.Format::month($period)" description="Lima produk dengan kelompok terbanyak. Satuan berbeda tidak dijumlahkan." flush>
    <x-slot:actions>
        <x-link :href="route('admin.statistics.economy')" class="gap-1 text-ui">
            Lihat semua di tab Ekonomi <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" />
        </x-link>
    </x-slot:actions>

    @if ($production->isEmpty())
        <p class="px-4 py-5 text-ui text-ink-soft md:px-6">Belum ada produksi yang dilaporkan.</p>
    @else
        <x-admin.table bare>
            <x-slot:head>
                <x-admin.table.heading>Produk</x-admin.table.heading>
                <x-admin.table.heading numeric>Produksi</x-admin.table.heading>
                <x-admin.table.heading numeric>Kelompok</x-admin.table.heading>
            </x-slot:head>
            @foreach ($production->take(5) as $row)
                <x-admin.table.row class="md:h-11!">
                    <x-admin.table.cell primary class="md:py-2">{{ $row['product'] }}</x-admin.table.cell>
                    <x-admin.table.cell label="Produksi" numeric class="md:py-2">
                        @if ($row['quantity'] === null)
                            <span class="text-ink-muted">Tidak diisi</span>
                        @else
                            {{ Format::quantity($row['quantity']) }} {{ $row['unit'] }}
                            @if ($row['filled'] < $row['organizations'])
                                <span class="block text-caption text-ink-muted">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                            @endif
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell label="Kelompok" numeric class="md:py-2">{{ Format::number($row['organizations']) }}</x-admin.table.cell>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>
    @endif
</x-admin.card>
