@props([
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'id' => null,
])

@php
    $key = $name ? \App\Support\FormField::key($name) : null;
    $id ??= $name ? \App\Support\FormField::id($name) : null;
    $invalid = $key && $errors->has($key);
    $current = $key ? old($key, $selected) : $selected;
@endphp

<div class="relative">
    <select
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->class([
            'block h-11 w-full appearance-none rounded-control border bg-paper pl-3 pr-9 text-body text-ink transition-colors md:h-10 md:text-ui',
            // Pilihan kosong (placeholder) tampil redup seperti placeholder input; daftar opsinya tetap ink.
            'has-[option:first-child:checked]:text-ink-muted *:text-ink' => $placeholder,
            'focus:outline-none focus:ring-1 disabled:bg-subtle disabled:text-ink-muted',
            'border-line-input focus:border-brand focus:ring-brand' => ! $invalid,
            'border-fix focus:border-fix focus:ring-fix' => $invalid,
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected($current !== null && (string) $value === (string) $current)>{{ $label }}</option>
        @endforeach
        {{ $slot }}
    </select>
    <x-heroicon-o-chevron-down class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" />
</div>
