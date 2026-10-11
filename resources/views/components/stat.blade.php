@props([
    'label',
    'value',
    'hint' => null,
    'tone' => null,
    // Ikon Heroicons dalam lingkaran brand-tint: hanya untuk kartu ringkasan (Bagian 7).
    'icon' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'rounded-card border border-line bg-paper p-4 md:p-5',
    'flex items-center gap-4' => $icon,
    'block transition-colors hover:bg-subtle' => $href,
]) }}>
    @if ($icon)
        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand" aria-hidden="true">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" />
        </span>
    @endif
    <span class="block min-w-0">
        @unless ($icon)
            <span class="block text-caption text-ink-soft">{{ $label }}</span>
        @endunless
        <span @class([
            'block text-page-title font-semibold tabular-nums',
            'mt-1' => ! $icon,
            match ($tone) {
                'ok' => 'text-ok',
                'wait' => 'text-wait',
                'fix' => 'text-fix',
                'idle' => 'text-idle',
                default => 'text-ink',
            },
        ])>{{ $value }}</span>
        @if ($icon)
            <span class="block text-ui text-ink-soft">{{ $label }}</span>
        @endif
        @if ($hint)
            <span class="mt-0.5 block text-caption text-ink-muted">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
