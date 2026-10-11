@props([
    'numeric' => false,
    'optional' => false,
    // Judul panjang boleh dua baris (tabel berkolom banyak), agar tabel lebih jarang perlu digeser di tablet.
    'wrap' => false,
])

{{-- Kolom angka: padding kiri/kanan ditulis terpisah (bukan px + pr) agar tepi kanannya selalu sama dengan sel. --}}
<th scope="col" {{ $attributes->class([
    $wrap ? 'h-10 py-2 align-middle text-caption font-semibold text-ink-soft' : 'h-10 whitespace-nowrap align-middle text-caption font-semibold text-ink-soft',
    'pl-4 pr-8 text-right' => $numeric,
    'px-4 text-left' => ! $numeric,
    'md:max-xl:hidden' => $optional,
]) }}>{{ $slot }}</th>
