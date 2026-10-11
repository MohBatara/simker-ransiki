@props([
    'title',
    'source' => 'Dari laporan terverifikasi.',
    // Catatan di bawah grafik, mis. kelompok dengan beberapa bidang usaha.
    'note' => null,
    // line | bar | bar-stacked | bar-horizontal
    'type' => 'bar',
    'format' => 'number',
    'labels' => [],
    // list of ['label' => ..., 'tone' => ..., 'data' => [...], 'notes' => ?[[...baris tooltip]], 'provisional' => ?[bool]]
    'datasets' => [],
    // ['position' => top|bottom, 'title' => ?string, 'lastValue' => bool, 'always' => bool]
    'legend' => null,
    // Judul sumbu nilai, mis. satuan produksi.
    'yTitle' => null,
    // ['columns' => [...], 'numeric' => [indeks kolom angka], 'rows' => [[...], ...]] — angka yang sama dengan grafik.
    'table' => ['columns' => [], 'numeric' => [], 'rows' => []],
    // Teks untuk pembaca layar yang membacakan isi grafik.
    'summary' => '',
    // Pesan bila belum ada data; grafik tidak ditampilkan.
    'empty' => null,
    'height' => 288,
    // Catatan tambahan dari data (mis. keterangan garis putus-putus).
    'footnote' => null,
    // Beberapa grafik kecil dalam satu kartu (mis. produk dengan satuan berbeda); setiap panel berisi kunci
    // yang sama dengan prop di atas + 'subtitle'.
    'panels' => null,
    // Hasil App\Support\Dashboards\StatisticsCharts::*; kuncinya mengisi prop di atas.
    'chart' => null,
])

{{--
    Kartu grafik (Chart.js, dimuat hanya di halaman yang punya grafik; lihat Alpine.data('chart') di app.js).
    Tombol "Lihat tabel" menampilkan angka yang sama dalam tabel; tabel selalu ikut tercetak.
    min-w-0 + wadah grafik berukuran tetap: canvas tidak boleh melebarkan grid/halaman (overflow di HP).
--}}
@php
    foreach (['type', 'format', 'labels', 'datasets', 'legend', 'yTitle', 'table', 'summary', 'empty', 'height', 'footnote', 'panels'] as $key) {
        if ($chart && array_key_exists($key, $chart)) {
            $$key = $chart[$key];
        }
    }
    $panels ??= [compact('type', 'format', 'labels', 'datasets', 'legend', 'yTitle', 'summary', 'height')];
    $configs = collect($panels)->map(fn (array $panel) => collect($panel)->only(['type', 'format', 'labels', 'datasets', 'legend', 'yTitle']))->all();
    $id = 'grafik-'.\Illuminate\Support\Str::slug($title);
    $numeric = $table['numeric'] ?? [];
@endphp

<div {{ $attributes->class('min-w-0') }} @unless ($empty) x-data="chart(@js($configs))" @endunless>
    <x-card :title="$title" :description="$source" stretch class="h-full">
        @unless ($empty)
            <x-slot:actions>
                <x-button variant="ghost" icon="table-cells" class="-my-2 print:hidden" x-on:click="showTable = ! showTable" x-bind:aria-expanded="showTable" aria-controls="{{ $id }}-tabel">
                    <span x-text="showTable ? 'Tutup tabel' : 'Lihat tabel'">Lihat tabel</span>
                </x-button>
            </x-slot:actions>
        @endunless

        @if ($empty)
            <p class="text-ink-soft">{{ $empty }}</p>
        @else
            <div class="space-y-4 print:hidden">
                @foreach ($panels as $index => $panel)
                    <div class="min-w-0">
                        @if ($panel['subtitle'] ?? null)
                            <h3 class="mb-2 text-ui font-semibold text-ink">{{ $panel['subtitle'] }}</h3>
                        @endif
                        <div class="relative w-full min-w-0" style="height: {{ (int) ($panel['height'] ?? $height) }}px">
                            <canvas data-panel="{{ $index }}" role="img" aria-label="{{ $panel['summary'] ?? $summary }}">{{ $panel['summary'] ?? $summary }}</canvas>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($note || $footnote)
                <div class="mt-auto space-y-1 pt-3 text-caption text-ink-soft">
                    @if ($footnote)
                        <p>{{ $footnote }}</p>
                    @endif
                    @if ($note)
                        <p>{{ $note }}</p>
                    @endif
                </div>
            @endif

            <div id="{{ $id }}-tabel" x-show="showTable" x-cloak class="mt-4 min-w-0 print:block!">
                <x-table bare>
                    <x-slot:head>
                        @foreach ($table['columns'] as $index => $column)
                            <x-table.heading :numeric="in_array($index, $numeric, true)">{{ $column }}</x-table.heading>
                        @endforeach
                    </x-slot:head>
                    @foreach ($table['rows'] as $row)
                        <x-table.row>
                            @foreach ($row as $index => $cell)
                                @if ($index === 0)
                                    <x-table.cell primary>{{ $cell }}</x-table.cell>
                                @elseif ($cell === '')
                                    {{-- Sel kosong (mis. Keterangan) tidak ditampilkan di kartu HP. --}}
                                    <x-table.cell class="max-md:hidden"></x-table.cell>
                                @else
                                    <x-table.cell :label="$table['columns'][$index] ?? null" :numeric="in_array($index, $numeric, true)">{{ $cell }}</x-table.cell>
                                @endif
                            @endforeach
                        </x-table.row>
                    @endforeach
                </x-table>
            </div>
        @endif
    </x-card>
</div>
