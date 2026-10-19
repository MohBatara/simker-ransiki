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
    // sm untuk konfirmasi biasa; md bila berisi isian (alasan, catatan).
    'size' => 'sm',
    'description' => null,
])

{{--
    Dialog konfirmasi seragam (x-admin.modal) untuk aksi berisiko (hapus, tutup kelompok, minta revisi, verifikasi). Buka
    dengan $dispatch('open-modal', name). Isi slot: kalimat penjelas dan, bila perlu, isian (alasan, catatan) beserta
    input tersembunyi _modal agar popup terbuka lagi saat validasi server gagal.
--}}
@php
    $formId = $form ?? 'form-'.$name;
    // Validasi server gagal untuk popup ini: buka lagi dengan isian lama.
    $show = $show || old('_modal') === $name;
@endphp

<x-admin.modal :name="$name" :title="$title" :description="$description" :size="$size" :show="$show">
    @if ($action || $bindAction)
        <form novalidate id="{{ $formId }}" method="POST" @if ($action) action="{{ $action }}" @endif @if ($bindAction) x-bind:action="{{ $bindAction }}" @endif class="space-y-4">
            @csrf
            @unless (strtoupper($method) === 'POST')
                @method($method)
            @endunless
            <input type="hidden" name="_modal" value="{{ $name }}">
            {{ $slot }}
        </form>
    @else
        <div class="space-y-4">{{ $slot }}</div>
    @endif
    <x-slot:footer>
        <x-button variant="secondary" x-on:click="requestClose()">Batal</x-button>
        <x-button type="submit" :form="$formId" :variant="$tone === 'danger' ? 'danger-solid' : 'primary'" :processing="$processing">{{ $confirm }}</x-button>
    </x-slot:footer>
</x-admin.modal>
