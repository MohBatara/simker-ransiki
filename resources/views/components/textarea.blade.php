@props([
    'name' => null,
    'value' => null,
    'rows' => 4,
    'id' => null,
])

@php
    $key = $name ? \App\Support\FormField::key($name) : null;
    $id ??= $name ? \App\Support\FormField::id($name) : null;
    $invalid = $key && $errors->has($key);
    $value = $key ? old($key, $value) : $value;
@endphp

<textarea
    rows="{{ $rows }}"
    @if ($name) name="{{ $name }}" @endif
    @if ($id) id="{{ $id }}" @endif
    @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    {{ $attributes->class([
        'block w-full rounded-control border bg-paper px-3 py-2 text-body text-ink transition-colors placeholder:text-ink-muted md:text-ui',
        'focus:outline-none focus:ring-1 disabled:bg-subtle disabled:text-ink-muted',
        'border-line-input focus:border-brand focus:ring-brand' => ! $invalid,
        'border-fix focus:border-fix focus:ring-fix' => $invalid,
    ]) }}
>{{ $value }}</textarea>
