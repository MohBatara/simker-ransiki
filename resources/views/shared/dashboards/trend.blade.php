{{--
    Laporan terverifikasi per bulan: kolom abu = kelompok wajib lapor, isi hijau tua = terverifikasi.
    Variabel: $trend, $area.
--}}
@use('App\Support\Format')

@php
    $max = max(1, $trend->max('obligated'));
@endphp

<x-card title="Laporan terverifikasi, 6 bulan" description="Dibandingkan dengan jumlah kelompok wajib lapor tiap bulan.">
    <ol
        class="grid h-44 grid-cols-6 items-end gap-2 md:gap-4"
        role="img"
        aria-label="{{ $trend->map(fn ($row) => Format::month($row['period']).': '.$row['verified'].' dari '.$row['obligated'])->join('; ') }}"
    >
        @foreach ($trend as $row)
            <li class="flex h-full flex-col justify-end">
                <a href="{{ route($area.'.reports.index', ['status' => 'verified', 'period' => $row['period']->format('Y-m')]) }}" class="group flex h-full flex-col justify-end rounded-control" aria-hidden="true" tabindex="-1">
                    <span class="relative block w-full rounded-t-control bg-subtle" style="height: {{ round($row['obligated'] / $max * 100, 2) }}%">
                        <span class="absolute inset-x-0 bottom-0 block rounded-t-control bg-brand transition-colors group-hover:bg-brand-hover" style="height: {{ $row['obligated'] ? round($row['verified'] / $row['obligated'] * 100, 2) : 0 }}%"></span>
                    </span>
                </a>
            </li>
        @endforeach
    </ol>
    <ol class="mt-2 grid grid-cols-6 gap-2 text-center md:gap-4">
        @foreach ($trend as $row)
            <li>
                <a href="{{ route($area.'.reports.index', ['status' => 'verified', 'period' => $row['period']->format('Y-m')]) }}" class="flex min-h-11 flex-col justify-center rounded-control hover:underline">
                    <span class="block text-caption text-ink-soft">{{ $row['period']->translatedFormat('M') }}<span class="sr-only"> {{ $row['period']->year }}</span></span>
                    <span class="block font-semibold tabular-nums text-ink">{{ $row['verified'] }}<span class="font-normal text-ink-muted">/{{ $row['obligated'] }}</span></span>
                </a>
            </li>
        @endforeach
    </ol>
</x-card>
