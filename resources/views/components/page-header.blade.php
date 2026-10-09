@props([
    'title',
    'description' => null,
    'back' => null,
    // Dipakai layout app: di HP judul sudah tampil di header atas, jadi h1 hanya untuk pembaca layar.
    'condensed' => false,
])

<div {{ $attributes->class([
    'flex flex-col gap-4 md:flex-row md:items-end md:justify-between',
    'max-md:hidden' => $condensed && ! $description && ! $back && ! isset($actions),
]) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 rounded-control text-caption text-ink-soft hover:text-ink">
                <x-heroicon-o-arrow-left class="size-4" />
                Kembali
            </a>
        @endif
        <h1 @class(['text-page-title font-semibold text-ink', 'max-md:sr-only' => $condensed])>{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-ui text-ink-soft">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 md:shrink-0">{{ $actions }}</div>
    @endisset
</div>
