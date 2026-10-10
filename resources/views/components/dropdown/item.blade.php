@props([
    'href' => null,
    'icon' => null,
])

@php
    $classes = 'flex min-h-11 w-full items-center gap-2 px-3 py-2 text-left text-ui text-ink transition-colors hover:bg-subtle md:min-h-10';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 text-ink-muted" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->except('type')->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 text-ink-muted" />
        @endif
        {{ $slot }}
    </button>
@endif
