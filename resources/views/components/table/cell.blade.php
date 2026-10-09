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
])

@php
    $showLabel = $label && ! $primary && ! $badge && ! $subtitle && ! $actions;
@endphp

<td
    @if ($showLabel) data-label="{{ $label }}" @endif
    {{ $attributes->class([
        'px-4 py-3 align-middle',
        'max-md:col-span-2 max-md:flex max-md:items-center max-md:justify-between max-md:gap-4 max-md:px-0 max-md:py-1 max-md:text-right' => $showLabel,
        'max-md:before:shrink-0 max-md:before:text-left max-md:before:text-ink-soft max-md:before:content-[attr(data-label)]' => $showLabel,
        'tabular-nums md:pr-8 md:text-right' => $numeric,
        'max-md:col-start-1 max-md:row-start-1 max-md:self-center max-md:p-0 max-md:font-semibold' => $primary,
        'max-md:col-start-2 max-md:row-start-1 max-md:self-center max-md:p-0' => $badge,
        'max-md:col-span-2 max-md:col-start-1 max-md:row-start-2 max-md:px-0 max-md:pb-2 max-md:pt-0.5 max-md:text-ink-soft' => $subtitle,
        'whitespace-nowrap md:text-right max-md:col-span-2 max-md:mt-2 max-md:border-t max-md:border-line max-md:px-0 max-md:pb-0 max-md:pt-3' => $actions,
        'md:max-xl:hidden' => $optional,
    ]) }}
>
    @if ($primary && $href)
        <a href="{{ $href }}" class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-brand max-md:after:rounded-card">{{ $slot }}</a>
    @else
        {{ $slot }}
    @endif
</td>
