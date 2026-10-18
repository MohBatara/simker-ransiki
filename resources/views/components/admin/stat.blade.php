@props([
    'label',
    'value',
    // Ikon Heroicons dalam lingkaran brand-tint.
    'icon' => null,
    'hint' => null,
    // Seluruh kartu menjadi tautan ke daftar yang tersaring.
    'href' => null,
    // Warna angka status (bila angka itu sendiri berarti status): success | warning | orange | danger.
    'tone' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

{{-- Kartu angka seragam: ikon dalam lingkaran tint, angka 28px, label, keterangan kecil opsional. --}}
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'group flex min-w-0 flex-col gap-3 rounded-tile border border-line bg-paper p-4 md:p-5',
    'transition-colors hover:border-line-strong hover:bg-subtle/60' => $href,
]) }}>
    <span class="flex items-center justify-between gap-3">
        @if ($icon)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand" aria-hidden="true">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" />
            </span>
        @endif
        @if ($href)
            <x-heroicon-o-arrow-up-right class="size-4 text-ink-muted transition-colors group-hover:text-brand" aria-hidden="true" />
        @endif
    </span>
    <span class="block min-w-0">
        <span @class([
            'block text-display-sm font-semibold tabular-nums',
            match ($tone) {
                'warning' => 'text-wait',
                'orange' => 'text-orange',
                'danger' => 'text-fix',
                'success' => 'text-ok',
                default => 'text-ink',
            },
        ])>{{ $value }}</span>
        <span class="mt-0.5 block text-ui text-ink-soft">{{ $label }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-caption text-ink-muted">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
