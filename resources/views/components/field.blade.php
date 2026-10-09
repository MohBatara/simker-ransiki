@props([
    'label',
    'for' => null,
    'hint' => null,
    'optional' => false,
])

@php
    $key = $for ? \App\Support\FormField::key($for) : null;
    $id = $for ? \App\Support\FormField::id($for) : null;
    $error = $key ? $errors->first($key) : null;
@endphp

<div {{ $attributes->class('space-y-1.5') }}>
    <label @if ($id) for="{{ $id }}" @endif class="block text-ui font-semibold text-ink">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-ink-muted">(opsional)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($error)
        <p id="{{ $id }}-error" class="text-caption text-fix">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-caption text-ink-muted">{{ $hint }}</p>
    @endif
</div>
