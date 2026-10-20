{{--
    Kartu "Pelaporan per OPD" (Dasbor Admin, tab Ringkasan). Angka dari ReportingStats::progressPerOpd() (kelompok dengan
    beberapa OPD dihitung di setiap OPD) dan progress() untuk "Seluruh distrik" (kelompok dihitung sekali).
    Satu baris per OPD (±76px, seluruh baris menaut ke daftar Laporan tersaring OPD + periode): singkatan + nama resmi,
    bilah bertumpuk 14px dengan tooltip per segmen dan ringkasan yang hanya menyebut status berisi, lalu persen
    terverifikasi + "X dari Y kelompok". Urut persen terverifikasi terendah dulu; OPD tanpa kelompok wajib lapor di akhir.
    HP: bertumpuk (nama, bilah, angka). Variabel: $perOpd, $progress, $period, $month.
--}}
@use('App\Support\Format')

@php
    // Warna bilah (atas permintaan; sama dengan legenda). Belum diajukan = abu bergaris tipis.
    $tones = [
        'verified' => ['Terverifikasi', 'terverifikasi', 'bg-brand'],
        'submitted' => ['Menunggu verifikasi', 'menunggu', 'bg-[#C9962E]'],
        'revision' => ['Perlu revisi', 'revisi', 'bg-[#A63A2B]'],
        'pending' => ['Belum diajukan', 'belum diajukan', 'bg-[#C9CEC9] bg-[repeating-linear-gradient(135deg,transparent_0_3px,rgb(255_255_255/0.55)_3px_5px)]'],
    ];
    $shape = fn (array $counts) => [
        'verified' => $counts['verified'],
        'submitted' => $counts['submitted'],
        'revision' => $counts['revision'],
        'pending' => $counts['draft'] + $counts['missing'],
        'total' => $counts['total'],
        'percent' => $counts['total'] > 0 ? (int) round($counts['verified'] / $counts['total'] * 100) : null,
    ];

    $rows = collect($perOpd)
        ->reject(fn (array $row) => ! $row['opd'] && $row['counts']['total'] === 0)
        ->map(fn (array $row) => [
            'short' => $row['opd'] ? ($row['opd']->short_name ?: $row['opd']->name) : 'Tanpa OPD pembina',
            'name' => $row['opd'] && $row['opd']->short_name && $row['opd']->short_name !== $row['opd']->name ? $row['opd']->name : null,
            'href' => route('admin.reports.index', ['opd' => $row['opd']?->id ?? 'none', 'period' => $month]),
            'district' => false,
            ...$shape($row['counts']),
        ])
        ->sortBy([
            fn (array $a, array $b) => ($a['percent'] === null) <=> ($b['percent'] === null),
            fn (array $a, array $b) => ($a['percent'] ?? 0) <=> ($b['percent'] ?? 0),
            fn (array $a, array $b) => strcmp($a['short'], $b['short']),
        ])
        ->push([
            'short' => 'Seluruh distrik',
            'name' => 'Setiap kelompok dihitung sekali',
            'href' => route('admin.reports.index', ['period' => $month]),
            'district' => true,
            ...$shape($progress),
        ]);
@endphp

<x-admin.card title="Pelaporan per OPD" :description="Format::month($period).' · kelompok dengan beberapa OPD dihitung di setiap OPD'" flush>
    <x-slot:meta>
        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-label text-ink-2" aria-label="Keterangan warna" data-opd-legend>
            @foreach ($tones as [$label, , $fill])
                <li class="inline-flex items-center gap-1.5"><span class="size-2.5 shrink-0 rounded-full {{ $fill }}" aria-hidden="true"></span>{{ $label }}</li>
            @endforeach
        </ul>
    </x-slot:meta>

    <ul class="border-t border-line" data-opd-rows>
        @foreach ($rows as $row)
            @php
                $parts = collect($tones)->filter(fn ($tone, string $key) => $row[$key] > 0);
                $summary = $parts->map(fn ($tone, string $key) => $row[$key].' '.$tone[1])->join(' · ');
            @endphp
            <li @class(['border-b border-line-soft last:border-b-0', 'bg-brand-50' => $row['district']]) data-opd-row="{{ $row['short'] }}">
                <a href="{{ $row['href'] }}" @class([
                    'grid min-h-19 grid-cols-1 gap-x-6 gap-y-2 px-4 py-3 transition-colors md:grid-cols-[15rem_minmax(0,1fr)_10rem] md:items-center md:px-5 lg:grid-cols-[16.25rem_minmax(0,1fr)_10rem]',
                    'hover:bg-hover' => ! $row['district'],
                    'hover:bg-brand-100/60' => $row['district'],
                ])>
                    <span class="min-w-0">
                        <span class="block font-semibold text-ink">{{ $row['short'] }}</span>
                        @if ($row['name'])
                            <span class="block truncate text-label text-ink-3" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        @endif
                    </span>

                    <span class="min-w-0">
                        <span class="flex h-3.5 w-full overflow-hidden rounded-full bg-subtle" role="img" aria-label="{{ $row['short'] }}: {{ $summary ?: 'tidak ada kelompok wajib lapor' }}">
                            @foreach ($parts as $key => [$label, , $fill])
                                <span class="h-full border-r-2 border-paper last:border-r-0 {{ $fill }}" style="width: {{ round($row[$key] / $row['total'] * 100, 2) }}%" title="{{ $label }}: {{ $row[$key] }} kelompok" data-segment="{{ $key }}"></span>
                            @endforeach
                        </span>
                        <span class="mt-1.5 block text-label text-ink-2">{{ $summary ?: 'Tidak ada kelompok wajib lapor' }}</span>
                    </span>

                    <span class="flex items-baseline gap-2 md:block md:text-right">
                        <span class="text-admin-title font-semibold tabular-nums text-ink">{{ $row['percent'] === null ? Format::orEmpty(null) : $row['percent'].'%' }}</span>
                        <span class="block text-label text-ink-3 max-md:inline">{{ Format::number($row['verified']) }} dari {{ Format::number($row['total']) }} kelompok</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</x-admin.card>
