@props([
    'label',
    'name' => null,
    'hint' => null,
    'optional' => false,
])

@php
    $key = $name ? \App\Support\FormField::key($name) : null;
    $error = $key ? $errors->first($key) : null;
@endphp

<fieldset {{ $attributes->class('space-y-1') }}>
    <legend class="text-ui font-semibold text-ink">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-ink-muted">(opsional)</span>
        @endif
    </legend>

    {{-- Lebar kolom mengikuti isi; maksimal 2 kolom mulai tablet. --}}
    <div class="grid gap-x-8 md:grid-cols-[repeat(2,max-content)]">
        {{ $slot }}
    </div>

    @if ($error)
        <p class="text-caption text-fix">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-caption text-ink-muted">{{ $hint }}</p>
    @endif
</fieldset>
