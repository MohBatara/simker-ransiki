@props([
    'label',
    'value',
    'hint' => null,
    'tone' => null,
])

<div {{ $attributes->class('rounded-card border border-line bg-paper p-4 md:p-5') }}>
    <p class="text-caption text-ink-soft">{{ $label }}</p>
    <p @class([
        'mt-1 text-page-title font-semibold tabular-nums',
        match ($tone) {
            'ok' => 'text-ok',
            'wait' => 'text-wait',
            'fix' => 'text-fix',
            'idle' => 'text-idle',
            default => 'text-ink',
        },
    ])>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-caption text-ink-muted">{{ $hint }}</p>
    @endif
</div>
