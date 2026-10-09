@props([
    'name' => null,
    'value' => '1',
    'checked' => false,
    'id' => null,
    'hint' => null,
])

@php
    $key = $name ? \App\Support\FormField::key($name) : null;
    $isArray = $name && str_ends_with($name, '[]');
    $id ??= $name ? \App\Support\FormField::id($name).($isArray ? '-'.$value : '') : null;

    // Setelah validasi gagal, ikuti input lama; checkbox kosong tidak terkirim sama sekali.
    if ($key && session()->hasOldInput()) {
        $checked = $isArray
            ? in_array((string) $value, array_map('strval', (array) old($key, [])), true)
            : (bool) old($key);
    }
@endphp

<label @if ($id) for="{{ $id }}" @endif class="flex min-h-11 cursor-pointer items-start gap-3 py-2.5 md:min-h-10">
    <input
        type="checkbox"
        value="{{ $value }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @checked($checked)
        {{ $attributes->class('mt-0.5 size-4 shrink-0 cursor-pointer accent-brand') }}
    >
    <span class="text-ui text-ink">
        {{ $slot }}
        @if ($hint)
            <span class="mt-0.5 block text-caption text-ink-muted">{{ $hint }}</span>
        @endif
    </span>
</label>
