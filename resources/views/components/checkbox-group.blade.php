@props([
    'label',
    'name' => null,
    'hint' => null,
    'optional' => false,
    // Isian wajib: tanda * merah di judul grup.
    'required' => false,
])

@php
    $key = $name ? \App\Support\FormField::key($name) : null;
    $error = $key ? $errors->first($key) : null;
    $id = $key ? \App\Support\FormField::id($key) : null;
@endphp

{{-- id = tujuan tautan ringkasan error (x-form-errors); grup bermasalah berbingkai merah. --}}
<fieldset @if ($id) id="{{ $id }}" tabindex="-1" @endif @if ($error) aria-describedby="{{ $id }}-error" @endif {{ $attributes->class('space-y-1 focus:outline-none') }}>
    <legend class="text-ui font-semibold text-ink [font-size:var(--label-text)] [font-weight:var(--label-weight)]">
        {{ $label }}
        @if ($required)
            <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
        @endif
    </legend>

    {{-- Lebar kolom mengikuti isi; maksimal 2 kolom mulai tablet. --}}
    <div @class(['grid gap-x-8 md:grid-cols-[repeat(2,max-content)]', 'rounded-control border border-fix px-3' => $error])>
        {{ $slot }}
    </div>

    @if ($error)
        <p id="{{ $id }}-error" data-field-error class="text-caption text-fix">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-caption text-ink-muted">{{ $hint }}</p>
    @endif
</fieldset>
