@props([
    // 'light' untuk latar terang, 'dark' untuk latar forest.
    'tone' => 'light',
])

<span {{ $attributes->class([
    'inline-flex h-7 items-center whitespace-nowrap rounded-full px-3 text-caption font-semibold',
    'bg-subtle text-ink-soft' => $tone === 'light',
    'border border-sun text-paper' => $tone === 'dark',
]) }}>{{ $slot }}</span>
