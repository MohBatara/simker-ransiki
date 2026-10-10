@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'back' => null,
    // Dipakai layout app: di HP judul dan tombol kembali sudah ada di header atas.
    'condensed' => false,
])

<div {{ $attributes->class([
    'flex flex-col gap-4 md:flex-row md:items-end md:justify-between',
    'max-md:hidden' => $condensed && ! $description && ! isset($actions) && ! isset($badge) && ! isset($meta),
]) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" @class(['mb-2 inline-flex items-center gap-1 rounded-control text-caption text-ink-soft hover:text-ink', 'max-md:hidden' => $condensed])>
                <x-heroicon-o-arrow-left class="size-4" />
                Kembali
            </a>
        @endif
        @if ($eyebrow)
            <p @class(['text-caption text-ink-soft', 'max-md:hidden' => $condensed])>{{ $eyebrow }}</p>
        @endif
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h1 @class(['text-page-title font-semibold text-ink', 'max-md:sr-only' => $condensed])>{{ $title }}</h1>
            @isset($badge)
                {{ $badge }}
            @endisset
        </div>
        @if ($description)
            <p class="mt-1 text-ui text-ink-soft">{{ $description }}</p>
        @endif
        @isset($meta)
            <div class="mt-3">{{ $meta }}</div>
        @endisset
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 md:shrink-0">{{ $actions }}</div>
    @endisset
</div>
