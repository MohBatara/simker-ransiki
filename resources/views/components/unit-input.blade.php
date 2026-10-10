@props([
    // Form biasa: nama field (dikirim lewat input tersembunyi).
    'name' => null,
    // Form Alpine: ekspresi x-model, mis. "item.production_unit" atau "record.unit".
    'model' => null,
    'value' => null,
    'id' => null,
    // Ekspresi JS untuk id yang dibuat di x-for.
    'bindId' => null,
    // Ekspresi JS kunci error untuk form Alpine (lihat x-field "live").
    'live' => null,
])

{{--
    Pilihan satuan dari App\Support\Units; kotak isian bebas muncul hanya bila "Lainnya" dipilih.
    Satuan lama yang tidak ada di daftar tampil sebagai "Lainnya" dengan teks aslinya.
--}}
@php
    $invalid = $live ? "!! error({$live})" : 'false';
    $idExpression = $bindId ?? ($id ? \Illuminate\Support\Js::from($id) : 'null');
@endphp

<div
    x-data="unitInput(@js($value), @js(App\Support\Units::LIST))"
    x-modelable="value"
    @if ($model) x-model="{{ $model }}" @endif
    {{ $attributes->class('space-y-2') }}
>
    <x-select
        x-model="choice"
        :options="App\Support\Units::options()"
        placeholder="Pilih satuan"
        aria-label="Satuan"
        x-bind:id="{{ $idExpression }}"
        x-bind:aria-invalid="{{ $invalid }}"
        x-bind:class="({{ $invalid }}) && 'border-fix! focus:border-fix! focus:ring-fix!'"
    />
    <x-input x-cloak x-show="choice === 'lainnya'" x-model="other" maxlength="30" placeholder="Tulis satuan" aria-label="Satuan lainnya" />
    @if ($name)
        <input type="hidden" name="{{ $name }}" x-bind:value="value">
    @endif
</div>
