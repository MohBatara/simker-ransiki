@props([
    'label',
    'value',
    // Ikon Heroicons dalam kotak brand-50.
    'icon' => null,
    'hint' => null,
    // Seluruh kartu menjadi tautan ke daftar yang tersaring.
    'href' => null,
    // Warna angka status (bila angka itu sendiri berarti status): success | warning | info | danger.
    'tone' => null,
    // Perbandingan setara (Statistics::matchedChange()[field]) untuk x-admin.change, opsional.
    'change' => null,
    'upIsGood' => true,
    // Versi ringkas satu baris (~88px) untuk halaman daftar, tab Ekonomi/Legalitas, strip detail kelompok.
    'compact' => false,
])

@php
    $tag = $href ? 'a' : 'div';
    $valueColor = match ($tone) {
        'warning' => 'text-wait',
        'info' => 'text-info',
        'danger' => 'text-fix',
        'success' => 'text-ok',
        default => 'text-ink',
    };
@endphp

{{-- Kartu angka: label 12px di atas, angka 22px semibold tabular, perubahan persen di sampingnya, keterangan kecil. --}}
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'group flex min-w-0 rounded-box border border-line bg-panel',
    'min-h-22 items-start gap-3 px-4 py-3.5' => $compact,
    'flex-col gap-3 p-4 md:p-5' => ! $compact,
    'transition-colors hover:border-brand-100 hover:bg-hover' => $href,
]) }}>
    @if ($icon)
        {{-- Ringkas di HP dua kolom: ikon disembunyikan agar angka dan label tetap terbaca. --}}
        <span @class(['flex size-10 shrink-0 items-center justify-center rounded-control bg-brand-50 text-brand', 'max-sm:hidden' => $compact]) aria-hidden="true">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" />
        </span>
    @endif
    <span class="block min-w-0 flex-1">
        <span class="block text-label text-ink-2 md:truncate">{{ $label }}</span>
        <span class="mt-0.5 flex flex-wrap items-baseline gap-x-2">
            <span class="text-[1.375rem] leading-7 font-semibold tabular-nums {{ $valueColor }}">{{ \App\Support\Format::orEmpty($value) }}</span>
            <x-admin.change :comparison="$change" :up-is-good="$upIsGood" />
        </span>
        {{-- Keterangan selalu ada agar tinggi dan letak angka sama di semua kartu. --}}
        <span class="block text-label text-ink-3 md:truncate" @if ($hint) title="{{ $hint }}" @endif>{!! $hint ? e($hint) : '&nbsp;' !!}</span>
    </span>
    @if ($href)
        <x-heroicon-o-arrow-up-right class="size-4 shrink-0 self-start text-ink-faint transition-colors group-hover:text-brand" aria-hidden="true" />
    @endif
</{{ $tag }}>
