@props([
    'tone' => 'idle',
])

<span {{ $attributes->class([
    'inline-flex h-6 items-center whitespace-nowrap rounded-control px-2 text-caption font-semibold',
    match ($tone) {
        'ok' => 'bg-ok-bg text-ok',
        'wait' => 'bg-wait-bg text-wait',
        'fix' => 'bg-fix-bg text-fix',
        default => 'bg-idle-bg text-idle',
    },
]) }}>{{ $slot }}</span>
