@props([
    // Perubahan persen dibanding bulan sebelumnya; null = tidak ditampilkan (salah satu bulan tanpa data terverifikasi).
    'value' => null,
    // Arti naik: true bila naik itu baik (penjualan, laba); false untuk biaya (naik ditandai merah).
    'upIsGood' => true,
])

@if ($value !== null)
    @php
        $up = $value > 0;
        $flat = $value === 0;
        $good = $flat ? null : ($up === $upIsGood);
    @endphp
    <span {{ $attributes->class([
        'inline-flex items-center gap-0.5 whitespace-nowrap text-label font-medium tabular-nums',
        'text-ok' => $good === true,
        'text-fix' => $good === false,
        'text-ink-3' => $good === null,
    ]) }}>
        @unless ($flat)
            <x-dynamic-component :component="$up ? 'heroicon-o-arrow-up' : 'heroicon-o-arrow-down'" class="size-3" aria-hidden="true" />
        @endunless
        {{ \App\Support\Format::number(abs($value)) }}%
        <span class="sr-only">{{ $flat ? 'tidak berubah' : ($up ? 'naik' : 'turun') }} dari bulan sebelumnya</span>
    </span>
@endif
