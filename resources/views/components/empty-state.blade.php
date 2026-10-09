@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('rounded-card border border-line bg-paper px-4 py-10 text-center md:px-5') }}>
    <p class="text-ui font-semibold text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-1 max-w-md text-ui text-ink-soft">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
