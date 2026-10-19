@props([
    'label',
    'for' => null,
    'hint' => null,
    'optional' => false,
    // Isian wajib: tanda * merah di label (isiannya tetap memakai atribut required sendiri).
    'required' => false,
    // Wajib bersyarat di form Alpine: ekspresi JS, mis. "! data.no_activity".
    'requiredIf' => null,
    // Form Alpine yang menyimpan sendiri (mis. laporan bulanan): ekspresi JS kunci error, dibaca lewat
    // method error(key) milik komponen Alpine induk. Contoh: "'income_sales'" atau "`items.${i}.revenue`".
    'live' => null,
    // Ekspresi JS untuk atribut for pada label di baris yang diulang (x-for).
    'bindFor' => null,
    // Ekspresi JS untuk label/keterangan yang berubah menurut isian form Alpine.
    'labelText' => null,
    'hintText' => null,
    // Kelas tambahan label, mis. "max-md:sr-only" untuk kotak cari yang sebaris dengan tombol di HP.
    'labelClass' => null,
])

@php
    $key = $for ? \App\Support\FormField::key($for) : null;
    $id = $for ? \App\Support\FormField::id($for) : null;
    $error = $key && ! $live ? $errors->first($key) : null;
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <label @if ($id) for="{{ $id }}" @endif @if ($bindFor) x-bind:for="{{ $bindFor }}" @endif @class(['block [font-size:var(--label-text)] [font-weight:var(--label-weight)] text-ink', $labelClass])>
        @if ($labelText)<span x-text="{{ $labelText }}">{{ $label }}</span>@else{{ $label }}@endif
        @if ($required)
            <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
        @elseif ($requiredIf)
            <span x-show="{{ $requiredIf }}"><span class="text-fix" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></span>
        @endif
        @if ($optional)
            <span class="font-normal text-ink-muted">(opsional)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($live)
        <p x-cloak x-show="error({{ $live }})" x-text="error({{ $live }})" class="[font-size:var(--hint-text)] text-fix"></p>
        @if ($hint)
            <p x-show="! error({{ $live }})" @if ($hintText) x-text="{{ $hintText }}" @endif class="[font-size:var(--hint-text)] text-ink-muted">{{ $hint }}</p>
        @endif
    @else
        @if ($hint)
            <p class="[font-size:var(--hint-text)] text-ink-muted">{{ $hint }}</p>
        @endif
        @if ($error)
            <p id="{{ $id }}-error" class="[font-size:var(--hint-text)] text-fix">{{ $error }}</p>
        @endif
    @endif
</div>
