@props([
    'name',
    'title',
    // Tujuan form; kosongkan bila form ada di slot sendiri (pakai prop "form").
    'action' => null,
    // Tujuan form dari ekspresi Alpine (mis. "deleteAction" di recordForm).
    'bindAction' => null,
    'method' => 'POST',
    // Id form di dalam slot bila tidak memakai "action".
    'form' => null,
    'confirm' => 'Lanjutkan',
    // danger = aksi yang menghapus/menutup; brand = aksi biasa (mis. minta revisi).
    'tone' => 'danger',
    'processing' => 'Memproses…',
    // Buka saat halaman dimuat (validasi gagal).
    'show' => false,
])

{{--
    Dialog konfirmasi seragam untuk aksi berisiko (hapus, tutup kelompok, minta revisi). Buka dengan
    $dispatch('open-modal', name). Isi slot: kalimat penjelas dan, bila perlu, isian (alasan, catatan).
--}}
@php
    $formId = $form ?? 'form-'.$name;
@endphp

<x-modal :name="$name" :title="$title" :show="$show">
    @if ($action || $bindAction)
        <form novalidate id="{{ $formId }}" method="POST" @if ($action) action="{{ $action }}" @endif @if ($bindAction) x-bind:action="{{ $bindAction }}" @endif class="space-y-4">
            @csrf
            @unless (strtoupper($method) === 'POST')
                @method($method)
            @endunless
            {{ $slot }}
        </form>
    @else
        <div class="space-y-4">{{ $slot }}</div>
    @endif
    <x-slot:footer>
        <x-button variant="secondary" x-on:click="$dispatch('close-modal', {{ \Illuminate\Support\Js::from($name) }})">Batal</x-button>
        <x-button type="submit" :form="$formId" :variant="$tone === 'danger' ? 'danger-solid' : 'primary'" :processing="$processing">{{ $confirm }}</x-button>
    </x-slot:footer>
</x-modal>
