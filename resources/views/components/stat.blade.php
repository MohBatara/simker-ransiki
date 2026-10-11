@props([
    'label',
    'value',
    'hint' => null,
    // Keterangan tambahan yang hanya tampil mulai tablet (mis. "sesuai filter"); di HP keterangan dibuat ringkas.
    'hintMd' => null,
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
    'flex flex-col items-start gap-3 md:flex-row md:items-center md:gap-4' => $icon,
    'block transition-colors hover:bg-subtle' => $href,
]) }}>
    @if ($icon)
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand md:size-12" aria-hidden="true">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 md:size-6" />
        </span>
    @endif
    <span class="block w-full min-w-0">
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
            <span class="block truncate text-ui text-ink-soft" title="{{ $label }}">{{ $label }}</span>
        @endif
        @if ($hint || $hintMd)
            <span @class(['mt-0.5 block text-caption text-ink-muted', 'max-md:hidden' => ! $hint])>
                {{ $hint }}@if ($hint && $hintMd)<span class="max-md:hidden">, </span>@endif
                @if ($hintMd)<span class="max-md:hidden">{{ $hintMd }}</span>@endif
            </span>
        @endif
    </span>
</{{ $tag }}>
