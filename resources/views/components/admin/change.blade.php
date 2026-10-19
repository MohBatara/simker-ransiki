@props([
    // Hasil Statistics::matchedChange()[field]: ['change', 'organizations', 'comparable', 'previous']; null = tidak tampil.
    'comparison' => null,
    // Arti naik: true bila naik itu baik (penjualan, laba); false untuk biaya (naik ditandai merah).
    'upIsGood' => true,
])

@if ($comparison)
    @if (! $comparison['comparable'])
        <span class="text-label text-ink-3" title="Kurang dari {{ \App\Support\Dashboards\Statistics::MIN_MATCHED }} kelompok terverifikasi di kedua bulan">belum bisa dibandingkan</span>
    @elseif ($comparison['change'] !== null)
        @php
            $value = $comparison['change'];
            $up = $value > 0;
            $flat = $value === 0;
            $good = $flat ? null : ($up === $upIsGood);
            $title = 'Dibanding '.\App\Support\Format::month($comparison['previous']).', dari '.$comparison['organizations'].' kelompok yang sama';
        @endphp
        <span title="{{ $title }}" {{ $attributes->class([
            'inline-flex cursor-help items-center gap-0.5 whitespace-nowrap text-label font-medium tabular-nums',
            'text-ok' => $good === true,
            'text-fix' => $good === false,
            'text-ink-3' => $good === null,
        ]) }}>
            @unless ($flat)
                <x-dynamic-component :component="$up ? 'heroicon-o-arrow-up' : 'heroicon-o-arrow-down'" class="size-3" aria-hidden="true" />
            @endunless
            {{ \App\Support\Format::number(abs($value)) }}%
            <span class="sr-only">{{ $flat ? 'tidak berubah' : ($up ? 'naik' : 'turun') }}. {{ $title }}.</span>
        </span>
    @endif
@endif
