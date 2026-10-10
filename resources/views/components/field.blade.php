@props([
    'label',
    'for' => null,
    'hint' => null,
    'optional' => false,
    // Form Alpine yang menyimpan sendiri (mis. laporan bulanan): ekspresi JS kunci error, dibaca lewat
    // method error(key) milik komponen Alpine induk. Contoh: "'income_sales'" atau "`items.${i}.revenue`".
    'live' => null,
    // Ekspresi JS untuk atribut for pada label di baris yang diulang (x-for).
    'bindFor' => null,
    // Ekspresi JS untuk label/keterangan yang berubah menurut isian form Alpine.
    'labelText' => null,
    'hintText' => null,
])

@php
    $key = $for ? \App\Support\FormField::key($for) : null;
    $id = $for ? \App\Support\FormField::id($for) : null;
    $error = $key && ! $live ? $errors->first($key) : null;
@endphp

<div {{ $attributes->class('space-y-1.5') }}>
    <label @if ($id) for="{{ $id }}" @endif @if ($bindFor) x-bind:for="{{ $bindFor }}" @endif class="block text-ui font-semibold text-ink">
        @if ($labelText)<span x-text="{{ $labelText }}">{{ $label }}</span>@else{{ $label }}@endif
        @if ($optional)
            <span class="font-normal text-ink-muted">(opsional)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($live)
        <p x-cloak x-show="error({{ $live }})" x-text="error({{ $live }})" class="text-caption text-fix"></p>
        @if ($hint)
            <p x-show="! error({{ $live }})" @if ($hintText) x-text="{{ $hintText }}" @endif class="text-caption text-ink-muted">{{ $hint }}</p>
        @endif
    @elseif ($error)
        <p id="{{ $id }}-error" class="text-caption text-fix">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-caption text-ink-muted">{{ $hint }}</p>
    @endif
</div>
