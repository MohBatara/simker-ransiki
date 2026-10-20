{{--
    Kartu "Pelaporan per OPD" (Dasbor Admin, tab Ringkasan). Angka dari ReportingStats::progressPerOpd() (kelompok dengan
    beberapa OPD dihitung di setiap OPD) dan progress() untuk "Seluruh distrik" (kelompok dihitung sekali).
    Satu warna (brand) dua tingkat: terverifikasi = brand, sudah diajukan belum terverifikasi (menunggu + perlu revisi) =
    brand 30%, sisanya track abu polos. Rincian netral (ink-3); hanya "perlu revisi" dan "belum diajukan" berwarna clay.
    Persen 28px ink, clay bila < 50%. Baris ±68px menaut ke daftar Laporan tersaring OPD + periode; urut persen terendah
    dulu; "Seluruh distrik" terakhir, dipisah garis tebal, teks tebal. HP: bertumpuk. Variabel: $perOpd, $progress, $period, $month.
--}}
@use('App\Support\Format')

@php
    // Rincian di bawah bilah: [kunci, teks, perlu perhatian (clay)]. Angka nol tidak ditampilkan.
    $details = [
        ['verified', 'terverifikasi', false],
        ['submitted', 'menunggu', false],
        ['revision', 'perlu revisi', true],
        ['pending', 'belum diajukan', true],
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
        <p class="mt-1 text-label text-ink-3" data-opd-legend>Hijau tua: terverifikasi · Hijau muda: sudah diajukan, belum diperiksa</p>
    </x-slot:meta>

    <ul class="border-t border-line" data-opd-rows>
        @foreach ($rows as $row)
            @php
                $submitted = $row['submitted'] + $row['revision'];
                $parts = collect($details)->filter(fn (array $detail) => $row[$detail[0]] > 0);
                $summary = $parts->map(fn (array $detail) => $row[$detail[0]].' '.$detail[1])->join(' · ');
                $width = fn (int $value) => $row['total'] > 0 ? round($value / $row['total'] * 100, 2) : 0;
            @endphp
            <li @class([
                'border-line-soft',
                'border-b' => ! $row['district'],
                'border-t-2 border-t-line-strong' => $row['district'],
            ]) data-opd-row="{{ $row['short'] }}">
                <a href="{{ $row['href'] }}" class="grid min-h-17 grid-cols-1 gap-x-6 gap-y-2 px-4 py-3 transition-colors md:py-2.5 hover:bg-hover md:grid-cols-[15rem_minmax(0,1fr)_9rem] md:items-center md:px-5 lg:grid-cols-[16.25rem_minmax(0,1fr)_9rem]">
                    <span class="min-w-0">
                        <span class="block font-semibold text-ink">{{ $row['short'] }}</span>
                        @if ($row['name'])
                            <span @class(['block truncate text-label', 'text-ink-3' => ! $row['district'], 'font-semibold text-ink-2' => $row['district']]) title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        @endif
                    </span>

                    <span class="min-w-0">
                        <span class="flex h-2 w-full overflow-hidden rounded-full bg-[#ECEEEA]" role="img" aria-label="{{ $row['short'] }}: {{ $summary ?: 'tidak ada kelompok wajib lapor' }}">
                            @if ($row['verified'] > 0)
                                <span class="h-full bg-brand" style="width: {{ $width($row['verified']) }}%" title="Terverifikasi: {{ $row['verified'] }} kelompok" data-segment="verified"></span>
                            @endif
                            @if ($submitted > 0)
                                <span class="h-full bg-brand/30" style="width: {{ $width($submitted) }}%" title="Sudah diajukan, belum terverifikasi: {{ $submitted }} kelompok" data-segment="submitted"></span>
                            @endif
                        </span>
                        <span @class(['mt-1.5 block text-label text-ink-3', 'font-semibold' => $row['district']])>
                            @forelse ($parts as [$key, $text, $attention])
                                @if (! $loop->first) · @endif<span @class(['text-[#A63A2B]' => $attention]) @if ($attention) data-attention @endif>{{ $row[$key] }} {{ $text }}</span>
                            @empty
                                Tidak ada kelompok wajib lapor
                            @endforelse
                        </span>
                    </span>

                    <span class="flex items-baseline gap-2 md:block md:text-right">
                        <span @class([
                            'text-[1.75rem] leading-8 font-semibold tabular-nums',
                            'text-[#A63A2B]' => $row['percent'] !== null && $row['percent'] < 50,
                            'text-ink' => $row['percent'] === null || $row['percent'] >= 50,
                        ]) data-percent>{{ $row['percent'] === null ? Format::orEmpty(null) : $row['percent'].'%' }}</span>
                        <span class="block text-label text-ink-3 max-md:inline">{{ Format::number($row['verified']) }} dari {{ Format::number($row['total']) }} kelompok</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</x-admin.card>
