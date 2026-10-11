@props([
    // 'sun' = tombol utama (teks ink), 'outline' = bergaris putih di latar gelap,
    // 'brand' = tombol utama di latar terang, 'outline-brand' = bergaris brand di latar terang.
    'variant' => 'sun',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    // Panah kanan setelah label (tautan ke halaman lain).
    'arrow' => false,
    // 'lg' = tombol utama halaman (44/48px), 'md' = tombol header (40px; 44px di HP).
    'size' => 'lg',
])

@php
    $classes = [
        'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-control text-ui font-semibold transition-colors',
        match ($size) {
            'md' => 'h-11 px-4 md:h-10',
            default => 'h-11 px-5 md:h-12',
        },
        match ($variant) {
            'sun' => 'bg-sun text-ink hover:bg-sun/85',
            'outline' => 'border border-paper text-paper hover:bg-paper/10',
            'brand' => 'bg-brand text-paper hover:bg-brand-hover',
            'outline-brand' => 'border border-brand bg-paper text-brand hover:bg-brand-tint',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-o-'.$icon" class="-ml-1 size-5" />@endif
        {{ $slot }}
        @if ($arrow)<x-heroicon-o-arrow-right class="-mr-1 size-4" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-o-'.$icon" class="-ml-1 size-5" />@endif
        {{ $slot }}
        @if ($arrow)<x-heroicon-o-arrow-right class="-mr-1 size-4" />@endif
    </button>
@endif
