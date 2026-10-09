@props([
    'title' => null,
    'description' => null,
    'flush' => false,
])

@php
    $hasHeader = $title || isset($actions);
@endphp

<section {{ $attributes->class('rounded-card border border-line bg-paper') }}>
    @if ($hasHeader)
        <header @class([
            'flex flex-wrap items-start justify-between gap-3 px-4 pt-4 md:px-5 md:pt-5',
            'border-b border-line pb-4 md:pb-5' => $flush,
        ])>
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-card-title font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-ui text-ink-soft">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class([
        'p-4 md:p-5' => ! $flush && ! $hasHeader,
        'px-4 pb-4 pt-4 md:px-5 md:pb-5' => ! $flush && $hasHeader,
    ])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-4 py-3 md:px-5">
            {{ $footer }}
        </footer>
    @endisset
</section>
