@props([
    'align' => 'right',
    'width' => 'w-56',
])

<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
    {{ $attributes->class('relative inline-block') }}
>
    <div x-on:click="open = ! open">{{ $trigger }}</div>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.100ms
        x-on:click="open = false"
        @class([
            'absolute z-30 mt-1 rounded-card border border-line bg-paper py-1 shadow-float',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
        ])
    >
        {{ $slot }}
    </div>
</div>
