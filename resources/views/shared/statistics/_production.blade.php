{{--
    Tab Ekonomi: "Produksi per produk" selebar penuh dan lengkap (Statistics::production, urutan sama dengan Dasbor).
    Kolom: Produk (min 260px) | Produksi (angka + satuan, kanan) | Kelompok (kiri, nama utuh; lebih dari satu = "3 kelompok"
    dengan daftar di tooltip) | Vs periode sebelumnya (kanan; rentang sama panjang tepat sebelumnya, perhitungan setara).
    Tidak ada satu pun baris yang bisa dibandingkan → kolom disembunyikan + kalimat kecil; sebagian → yang tidak bisa diberi
    "baru" kecil ink-3. Cari (nama produk/kelompok) dan "Tampilkan semua (N)" setelah 15 baris lewat Alpine, tanpa memuat ulang.
    Variabel: $production.
--}}
@use('App\Support\Format')

@php
    $limit = 15;
    $comparable = $production->contains(fn (array $row) => $row['change']['status'] === 'compared');
    $previous = $production->first()['change']['previous'] ?? null;
@endphp

<div x-data="{ q: '', all: false }" data-production-table>
    <x-admin.card title="Produksi per produk" description="Produk yang paling banyak diproduksi kelompok, dari laporan terverifikasi. Satuan berbeda tidak dijumlahkan." flush>
        @if ($production->isNotEmpty())
            <x-slot:actions>
                <label class="sr-only" for="cari-produksi">Cari produk atau kelompok</label>
                <x-input type="search" id="cari-produksi" x-model.debounce.150ms="q" placeholder="Cari produk atau kelompok" class="w-64 max-sm:w-[min(20rem,calc(100vw-4rem))] md:h-8!" data-no-dirty />
            </x-slot:actions>
        @endif

        @if ($production->isEmpty())
            <p class="px-4 pb-4 text-ink-soft md:px-5">Belum ada laporan terverifikasi yang mengisi produksi pada rentang ini.</p>
        @else
            @unless ($comparable)
                <p class="px-4 pb-3 text-label text-ink-3 md:px-5" data-no-comparison>Belum ada data periode sebelumnya untuk dibandingkan.</p>
            @endunless

            <x-admin.table bare class="max-md:p-4" label="Tabel produksi per produk">
                <x-slot:head>
                    <x-admin.table.heading class="md:min-w-65">Produk</x-admin.table.heading>
                    <x-admin.table.heading numeric fit>Produksi</x-admin.table.heading>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    @if ($comparable)
                        <x-admin.table.heading numeric fit><span title="Dibanding {{ $previous }}, dari kelompok yang sama">Vs periode sebelumnya</span></x-admin.table.heading>
                    @endif
                </x-slot:head>
                @foreach ($production as $row)
                    <x-admin.table.row
                        x-show="q === '' ? (all || {{ $loop->index }} < {{ $limit }}) : $el.dataset.search.includes(q.toLowerCase().trim())"
                        data-search="{{ mb_strtolower($row['product'].' '.implode(' ', $row['organization_names'])) }}"
                    >
                        <x-admin.table.cell primary class="md:min-w-65">{{ $row['product'] }}</x-admin.table.cell>
                        <x-admin.table.cell label="Produksi" numeric fit inline>
                            @if ($row['quantity'] === null)
                                <span class="text-ink-3">Tidak diisi</span>
                            @else
                                {{ Format::quantity($row['quantity']) }} {{ $row['unit'] ?? 'tanpa satuan' }}
                                @if ($row['filled'] < $row['organizations'])
                                    <span class="block text-label text-ink-3">diisi {{ $row['filled'] }} dari {{ $row['organizations'] }} kelompok</span>
                                @endif
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell label="Kelompok" inline>@include('shared.dashboards._production-cells', ['cell' => 'organizations', 'full' => true])</x-admin.table.cell>
                        @if ($comparable)
                            <x-admin.table.cell label="Vs periode sebelumnya" numeric fit inline>@include('shared.dashboards._production-cells', ['cell' => 'change', 'quiet' => true])</x-admin.table.cell>
                        @endif
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>

            @if ($production->count() > $limit)
                <div class="border-t border-line-soft px-4 py-3 md:px-5" x-show="! all && q === ''">
                    <x-button variant="secondary" x-on:click="all = true" data-show-all>Tampilkan semua ({{ Format::number($production->count()) }})</x-button>
                </div>
            @endif
        @endif
    </x-admin.card>
</div>
