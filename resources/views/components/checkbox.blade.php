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

<label @if ($id) for="{{ $id }}" @endif class="flex min-h-11 cursor-pointer items-start gap-3 py-3 md:min-h-10">
    <span class="relative flex size-5 shrink-0">
        <input
            type="checkbox"
            value="{{ $value }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            @checked($checked)
            {{ $attributes->class([
                'peer size-5 cursor-pointer appearance-none rounded-control border border-line-input bg-paper transition-colors',
                'checked:border-brand checked:bg-brand disabled:cursor-not-allowed disabled:bg-subtle',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
            ]) }}
        >
        <x-heroicon-o-check class="pointer-events-none absolute inset-0.5 hidden size-4 stroke-2 text-paper peer-checked:block" />
    </span>
    <span class="text-ui text-ink">
        {{ $slot }}
        @if ($hint)
            <span class="mt-0.5 block text-caption text-ink-muted">{{ $hint }}</span>
        @endif
    </span>
</label>
