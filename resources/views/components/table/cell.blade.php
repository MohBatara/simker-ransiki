@aware([
    'href' => null,
])

@props([
    'label' => null,
    'numeric' => false,
    'optional' => false,
    // Peran sel di kartu HP: judul (kiri atas), badge (kanan atas), atau subjudul di bawah judul.
    'primary' => false,
    'badge' => false,
    'subtitle' => false,
    // Tombol aksi untuk daftar tanpa halaman detail (mis. data master); di HP jadi baris paling bawah kartu.
    'actions' => false,
    // Kartu HP: "Label: nilai" rata kiri dalam satu baris (mis. "Jumlah: 1.384 ekor"), bukan label kiri–nilai kanan.
    'inline' => false,
])

@php
    $showLabel = $label && ! $primary && ! $badge && ! $subtitle && ! $actions;
@endphp

<td
    @if ($showLabel) data-label="{{ $label }}" @endif
    {{ $attributes->class([
        'py-3 align-middle',
        'px-4' => ! $numeric,
        'pl-4 pr-4 md:pr-8' => $numeric,
        'max-md:col-span-2 max-md:flex max-md:items-center max-md:justify-between max-md:gap-4 max-md:px-0 max-md:py-1 max-md:text-right' => $showLabel && ! $inline,
        'max-md:before:shrink-0 max-md:before:text-left max-md:before:text-ink-soft max-md:before:content-[attr(data-label)]' => $showLabel && ! $inline,
        "max-md:col-span-2 max-md:block max-md:px-0 max-md:py-0.5 max-md:text-left max-md:before:text-ink-soft max-md:before:content-[attr(data-label)_':_']" => $showLabel && $inline,
        'tabular-nums md:text-right' => $numeric,
        'max-md:col-start-1 max-md:row-start-1 max-md:self-center max-md:p-0 max-md:font-semibold' => $primary,
        // Tautan baris diperluas hanya sampai sel ini: Safari tidak menjadikan <tr> berposisi relative sebagai
        // acuan, jadi lapisan klik bisa lepas dari barisnya. Sisa baris diteruskan lewat JS (resources/js/app.js).
        'relative' => $primary && $href,
        'max-md:col-start-2 max-md:row-start-1 max-md:self-center max-md:p-0' => $badge,
        'max-md:col-span-2 max-md:col-start-1 max-md:row-start-2 max-md:px-0 max-md:pb-2 max-md:pt-0.5 max-md:text-ink-soft' => $subtitle,
        'whitespace-nowrap md:text-right max-md:col-span-2 max-md:mt-2 max-md:border-t max-md:border-line max-md:px-0 max-md:pb-0 max-md:pt-3' => $actions,
        'md:max-xl:hidden' => $optional,
    ]) }}
>
    @if ($primary && $href)
        <a href="{{ $href }}" data-row-link class="after:absolute after:inset-0 focus-visible:outline-none">{{ $slot }}</a>
    @else
        {{ $slot }}
    @endif
</td>
