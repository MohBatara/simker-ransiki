@props([
    'href' => null,
    'icon' => null,
])

@php
    $classes = 'flex w-full items-center gap-2 px-3 py-2 text-left text-ui text-ink transition-colors hover:bg-subtle';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 text-ink-muted" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 text-ink-muted" />
        @endif
        {{ $slot }}
    </button>
@endif
