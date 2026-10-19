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

{{-- Kepala kolom: 40px berlatar head, 11px kapital ink-3 (5,0:1), menempel di atas layar (kecuali tabel bergulir). --}}
<th scope="col" {{ $attributes->class([
    'h-10 border-b border-line bg-head align-middle text-micro font-semibold tracking-wide text-ink-3 uppercase',
    'first:rounded-tl-[9px] last:rounded-tr-[9px]' => ! $bare,
    'md:sticky md:top-0 md:z-10' => ! $label,
    'whitespace-nowrap' => ! $wrap,
    'py-2' => $wrap,
    'pl-4 pr-8 text-right' => $numeric,
    'px-4 text-left' => ! $numeric,
    'md:max-xl:hidden' => $optional,
]) }}>{{ $slot }}</th>
