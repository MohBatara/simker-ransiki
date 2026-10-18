@aware([
    'label' => null,
    'bare' => false,
])

@props([
    'numeric' => false,
    'optional' => false,
    // Judul panjang boleh dua baris.
    'wrap' => false,
])

{{-- Kepala kolom: teks kecil kapital muted (ink-muted 5,2:1), menempel di bawah bilah atas (kecuali tabel bergulir). --}}
<th scope="col" {{ $attributes->class([
    'h-11 border-b border-line bg-paper align-middle text-caption font-semibold tracking-wide text-ink-muted uppercase first:rounded-tl-tile',
    'sticky top-16 z-10' => ! $label,
    'whitespace-nowrap' => ! $wrap,
    'py-2' => $wrap,
    'pl-4 pr-8 text-right' => $numeric,
    'px-4 text-left' => ! $numeric,
    'md:max-xl:hidden' => $optional,
]) }}>{{ $slot }}</th>
