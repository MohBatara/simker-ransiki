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
        'block h-11 w-full rounded-control border bg-paper px-3 text-body text-ink transition-colors placeholder:text-ink-muted md:h-10 md:text-ui',
        'focus:outline-none focus:ring-1 disabled:bg-subtle disabled:text-ink-muted',
        'border-line-input focus:border-brand focus:ring-brand' => ! $invalid,
        'border-fix focus:border-fix focus:ring-fix' => $invalid,
    ]) }}
>
