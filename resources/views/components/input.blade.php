@props([
    'name' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    // Form Alpine: ekspresi JS; bila bernilai benar, kontrol tampil tidak valid (mis. "error('costs')").
    'invalidIf' => null,
])

@php
    $key = $name ? \App\Support\FormField::key($name) : null;
    $id ??= $name ? \App\Support\FormField::id($name) : null;
    $invalid = $key && $errors->has($key);
    $keepsValue = ! in_array($type, ['password', 'file']);
    if ($key && $keepsValue) {
        $value = old($key, $value);
    }
@endphp

{{-- Teks 16px di HP mencegah Safari iOS memperbesar layar saat input difokuskan. --}}
<input
    type="{{ $type }}"
    @if ($name) name="{{ $name }}" @endif
    @if ($id) id="{{ $id }}" @endif
    @if ($keepsValue && $value !== null) value="{{ $value }}" @endif
    @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    @if ($invalidIf) x-bind:aria-invalid="!! ({{ $invalidIf }})" x-bind:class="({{ $invalidIf }}) && 'border-fix! focus:border-fix! focus:ring-fix!'" @endif
    {{ $attributes->class([
        'block w-full rounded-control border bg-paper px-3 text-body text-ink transition-colors placeholder:text-ink-muted md:text-ui',
        'h-11 md:h-10' => $type !== 'file',
        // Pemilih file: tinggi mengikuti tombol "Pilih file" bawaan browser, digayakan seperti tombol sekunder.
        'min-h-11 py-1.5 text-ink-soft file:mr-3 file:h-8 file:cursor-pointer file:rounded-control file:border file:border-line-strong file:bg-paper file:px-3 file:text-ui file:font-semibold file:text-ink hover:file:bg-subtle md:min-h-10' => $type === 'file',
        'focus:outline-none focus:ring-1 disabled:bg-subtle disabled:text-ink-muted',
        'border-line-input focus:border-brand focus:ring-brand' => ! $invalid,
        'border-fix focus:border-fix focus:ring-fix' => $invalid,
    ]) }}
>
