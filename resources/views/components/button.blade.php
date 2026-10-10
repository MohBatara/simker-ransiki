@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    // Teks saat form sedang dikirim (lihat penanganan "submit" di resources/js/app.js). Bawaan: "Memproses…".
    'processing' => null,
])

@php
    $classes = [
        'inline-flex h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-control border px-4 text-ui font-semibold transition-colors md:h-10',
        'disabled:pointer-events-none disabled:opacity-50',
        match ($variant) {
            'primary' => 'border-brand bg-brand text-paper hover:border-brand-hover hover:bg-brand-hover',
            'secondary' => 'border-line-strong bg-paper text-ink hover:bg-subtle',
            'ghost' => 'border-transparent bg-transparent text-ink-soft hover:bg-subtle hover:text-ink',
            'danger' => 'border-line-strong bg-paper text-fix hover:bg-fix-bg',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="-ml-1 size-5" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @if ($processing) data-processing="{{ $processing }}" @endif {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="-ml-1 size-5" />
        @endif
        <span data-button-label>{{ $slot }}</span>
    </button>
@endif
