@props([
    // Ekspresi x-model, mis. "data.income_sales".
    'model',
    // money (Rp, bulat), integer (bulat), decimal (jumlah, koma desimal).
    'kind' => 'integer',
    'signed' => false,
    // Ekspresi JS kunci error untuk form Alpine (lihat x-field "live").
    'live' => null,
])

@php
    $options = $signed ? '{ signed: true }' : '{}';
    $preview = "\$number({$model}, '{$kind}', {$options})";
    $invalid = ($live ? "!! error({$live}) || " : '')."!! {$preview}?.invalid";
    // Keyboard angka di HP. Kolom yang bisa negatif memakai keyboard biasa: keyboard angka iOS tanpa tanda minus.
    $inputmode = $signed ? 'text' : ($kind === 'decimal' ? 'decimal' : 'numeric');
@endphp

<div class="space-y-1">
    <x-input
        type="text"
        :inputmode="$inputmode"
        autocomplete="off"
        x-model="{{ $model }}"
        :invalid-if="$invalid"
        {{ $attributes }}
    />
    <p x-cloak x-show="{{ $preview }}" x-text="{{ $preview }}?.text" x-bind:class="{{ $preview }}?.invalid ? 'text-fix' : 'text-ink-muted'" class="text-caption tabular-nums"></p>
</div>
