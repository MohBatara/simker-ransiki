{{--
    Kartu "Ekonomi <bulan>" dasbor Ringkasan (Admin dan OPD, 8 kolom): tiga angka (Penjualan, Biaya, Laba) dengan persen
    perubahan dari bulan sebelumnya (hanya bila kedua bulan punya data terverifikasi), cakupan laporan terverifikasi, lalu
    grafik garis 6 bulan Penjualan dan Biaya. Angka hanya dari laporan terverifikasi; NULL tidak dihitung 0.
    Variabel: $economy (bentuk DistrictStats::economy), $economyTrend (Statistics::recentEconomy), $economyChange
    (Statistics::matchedChange: perbandingan setara), $period, $area,
    $class (opsional).
--}}
@use('App\Support\Format')
@use('App\Support\Dashboards\Statistics')
@use('App\Support\Dashboards\StatisticsCharts')

@php
    $reports = $economy['reports'];
    $obligated = $economy['obligated'];
    $percent = $obligated > 0 ? (int) round($reports / $obligated * 100) : 0;
    $verifiedUrl = route($area.'.reports.index', ['status' => 'verified', 'period' => $period->format('Y-m')]);
    $numbers = [
        'income_sales' => ['Penjualan', true],
        'costs' => ['Biaya', false],
        'profit' => ['Laba', true],
    ];
    $other = $economy['totals']['income_other'];
    $notes = [
        'Hanya dari laporan terverifikasi.',
        'Angka kosong tidak dihitung sebagai nol.',
        'Laba hanya dari laporan yang mengisi penjualan, pendapatan lain, dan biaya.',
        'Persen dibanding bulan sebelumnya, hanya dari kelompok yang terverifikasi di kedua bulan (minimal '.Statistics::MIN_MATCHED.' kelompok).',
    ];
    $chart = StatisticsCharts::economyTrend($economyTrend);
    // "Belum bisa dibandingkan" ditulis sekali di bawah ketiga angka, bukan di tiap angka.
    $incomparable = collect($numbers)->keys()->map(fn ($field) => $economyChange[$field] ?? null)->first(fn ($comparison) => $comparison && ! $comparison['comparable']);
@endphp

<x-admin.card :title="'Ekonomi '.Format::month($period)" description="Dari laporan terverifikasi" stretch :class="$class ?? null">
    <x-slot:actions>
        <span class="inline-flex h-8 items-center rounded-control border border-line px-2.5 text-label text-ink-2">6 bulan terakhir</span>
        {{-- Catatan cara menghitung sebagai tooltip (hover/fokus), bukan di badan kartu. --}}
        <span class="group relative inline-flex">
            <button type="button" class="flex size-11 items-center justify-center rounded-control text-ink-3 transition-colors hover:bg-subtle hover:text-ink md:size-8" aria-label="Cara menghitung" aria-describedby="catatan-ekonomi">
                <x-heroicon-o-information-circle class="size-5" />
            </button>
            <span id="catatan-ekonomi" role="tooltip" class="invisible absolute right-0 top-full z-20 mt-1 w-72 rounded-card bg-ink px-3 py-2 text-caption text-paper opacity-0 shadow-float transition-opacity group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                @foreach ($notes as $note)
                    <span class="block">{{ $note }}</span>
                @endforeach
            </span>
        </span>
    </x-slot:actions>

    <div class="flex flex-1 flex-col gap-5">
        @if ($reports === 0)
            <div class="flex flex-col items-start gap-3 rounded-box border border-line-soft bg-head p-4 sm:flex-row sm:items-center">
                <p class="min-w-0 flex-1 text-caption text-ink">Belum ada laporan terverifikasi untuk {{ \App\Support\Format::orEmpty(Format::month($period)) }}.</p>
                <x-button variant="secondary" :href="route($area.'.verifications.index')">Buka laporan yang menunggu verifikasi</x-button>
            </div>
        @else
            {{-- Tiga angka utama. Singkatan rb/jt/M hanya di sini; nilai lengkap di tooltip. --}}
            <dl class="grid gap-4 sm:grid-cols-3">
                @foreach ($numbers as $field => [$label, $upIsGood])
                    @php
                        $total = $economy['totals'][$field];
                        $loss = $total['sum'] !== null && $total['sum'] < 0;
                        $short = $total['sum'] === null ? null : ($loss ? 'Rugi ' : '').Format::rupiahShort($total['sum']);
                        $full = $total['sum'] === null ? null : ($loss ? 'Rugi Rp '.Format::number(abs($total['sum'])) : Format::rupiah($total['sum']));
                        $comparison = ($economyChange[$field]['comparable'] ?? false) ? $economyChange[$field] : null;
                    @endphp
                    <div @class(['min-w-0', 'sm:border-l sm:border-line-soft sm:pl-4' => ! $loop->first])>
                        <dt class="text-label text-ink-2">{{ $label }}</dt>
                        <dd class="mt-0.5 min-w-0">
                            @if ($total['sum'] === null)
                                <span class="block text-caption text-ink-2">Belum ada yang mengisi</span>
                            @else
                                <span class="flex flex-wrap items-baseline gap-x-2">
                                    <a href="{{ $verifiedUrl }}" title="{{ $full }}" @class([
                                        'text-[1.375rem] leading-7 font-semibold whitespace-nowrap tabular-nums hover:underline',
                                        'text-fix' => $loss,
                                        'text-ink' => ! $loss,
                                    ])>{{ $short }}<span class="sr-only"> ({{ $full }})</span></a>
                                    <x-admin.change :comparison="$comparison" :up-is-good="$upIsGood" />
                                </span>
                                @if ($total['filled'] < $reports)
                                    <span class="block text-label text-ink-3">diisi {{ $total['filled'] }} dari {{ $reports }} laporan</span>
                                @endif
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
            @if ($incomparable)
                <p class="-mt-2 text-label text-ink-3" data-incomparable>
                    Belum bisa dibandingkan dengan {{ Format::month($incomparable['previous']) }}: kurang dari {{ Statistics::MIN_MATCHED }} kelompok terverifikasi di kedua bulan.
                </p>
            @endif

            {{-- Cakupan: berapa kelompok wajib lapor yang laporannya sudah terverifikasi. --}}
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-caption">
                    <p class="text-ink-2">
                        <a href="{{ $verifiedUrl }}" class="font-semibold tabular-nums text-ink hover:underline">{{ \App\Support\Format::orEmpty(Format::number($reports)) }} dari {{ \App\Support\Format::orEmpty(Format::number($obligated)) }}</a>
                        kelompok wajib lapor terverifikasi
                        @if ($other['sum'] !== null)
                            <span class="whitespace-nowrap">· pendapatan lain {{ \App\Support\Format::orEmpty(Format::rupiah($other['sum'])) }}</span>
                        @endif
                    </p>
                    <span class="shrink-0 font-semibold tabular-nums text-ink">{{ $percent }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="Cakupan laporan terverifikasi">
                    <div class="h-full rounded-full bg-brand" style="width: {{ min(100, $percent) }}%"></div>
                </div>
                @if ($percent < 50)
                    <p class="text-label text-wait">Angka belum mewakili seluruh kelompok.</p>
                @endif
            </div>
        @endif

        {{-- Grafik garis Penjualan dan Biaya 6 bulan (Chart.js); angka yang sama ada di "Lihat tabel". --}}
        @if ($chart['empty'])
            <p class="text-caption text-ink-2">{{ $chart['empty'] }}</p>
        @else
            <div x-data="chart(@js([collect($chart)->only(['type', 'format', 'labels', 'datasets', 'legend'])]))" class="mt-auto min-w-0">
                <div class="relative w-full min-w-0" style="height: 220px">
                    <canvas data-panel="0" role="img" aria-label="{{ $chart['summary'] }}">{{ $chart['summary'] }}</canvas>
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-label text-ink-3">
                    <p>{{ $chart['footnote'] }}</p>
                    <button type="button" class="inline-flex min-h-11 items-center gap-1 font-medium text-brand hover:underline md:min-h-8 print:hidden" x-on:click="showTable = ! showTable" x-bind:aria-expanded="showTable" aria-controls="grafik-ekonomi-tabel">
                        <x-heroicon-o-table-cells class="size-4" aria-hidden="true" />
                        <span x-text="showTable ? 'Tutup tabel' : 'Lihat tabel'">Lihat tabel</span>
                    </button>
                </div>
                <div id="grafik-ekonomi-tabel" x-show="showTable" x-cloak class="mt-3 min-w-0 print:block!">
                    <x-admin.table label="Tabel penjualan dan biaya">
                        <x-slot:head>
                            @foreach ($chart['table']['columns'] as $index => $column)
                                <x-admin.table.heading :numeric="in_array($index, $chart['table']['numeric'], true)">{{ $column }}</x-admin.table.heading>
                            @endforeach
                        </x-slot:head>
                        @foreach ($chart['table']['rows'] as $row)
                            <x-admin.table.row>
                                @foreach ($row as $index => $cell)
                                    @if ($index === 0)
                                        <x-admin.table.cell primary>{{ $cell }}</x-admin.table.cell>
                                    @elseif ($cell === '')
                                        <x-admin.table.cell class="max-md:hidden"></x-admin.table.cell>
                                    @else
                                        <x-admin.table.cell :label="$chart['table']['columns'][$index]" :numeric="in_array($index, $chart['table']['numeric'], true)">{{ \App\Support\Format::orEmpty($cell) }}</x-admin.table.cell>
                                    @endif
                                @endforeach
                            </x-admin.table.row>
                        @endforeach
                    </x-admin.table>
                </div>
            </div>
        @endif
    </div>
</x-admin.card>
