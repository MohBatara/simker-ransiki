@props([
    // 'sun' = tombol utama di latar gelap (teks ink), 'outline' = bergaris putih di latar gelap.
    'variant' => 'sun',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $classes = [
        'inline-flex h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-control px-5 text-ui font-semibold transition-colors md:h-12',
        match ($variant) {
            'sun' => 'bg-sun text-ink hover:bg-sun/85',
            'outline' => 'border border-paper text-paper hover:bg-paper/10',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-o-'.$icon" class="-ml-1 size-5" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-o-'.$icon" class="-ml-1 size-5" />@endif
        {{ $slot }}
    </button>
@endif
