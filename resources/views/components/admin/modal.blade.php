@props([
    'name',
    'title' => null,
    // Satu baris keterangan kecil di bawah judul.
    'description' => null,
    // sm 440px (konfirmasi), md 560px (form pendek), lg 760px (pratinjau/detail ringkas).
    'size' => 'md',
    // Buka saat halaman dimuat (mis. validasi server gagal: old('_modal') === name).
    'show' => false,
    // Tanya "Buang perubahan?" sebelum menutup bila isian di dalamnya sudah diubah.
    'guard' => true,
])

@php
    $width = match ($size) {
        'sm' => 'md:max-w-[440px]',
        'lg' => 'md:max-w-[760px]',
        default => 'md:max-w-[560px]',
    };
@endphp

{{--
    SATU komponen popup area aplikasi: <dialog> di tengah layar (role dialog, aria-modal, fokus terkunci, halaman belakang
    tidak bisa digulir), latar forest 40% + blur tipis, muncul fade + skala 96% ke 100% dalam 180ms. Buka/tutup dengan
    $dispatch('open-modal', name) / $dispatch('close-modal', name); tutup juga lewat Esc, tombol X, atau klik latar. Bila
    isian sudah diubah (guard), muncul "Buang perubahan?" dulu. Fokus kembali ke tombol pemicu setelah ditutup.
    Judul dinamis (Alpine) lewat slot "heading". Event "modal-closed" dikirim setelah popup tertutup.
    Kepala: judul 16px + satu baris keterangan; badan menggulir sendiri (maks 85vh, HP 90vh); slot "footer" menempel
    di bawah (Batal sekunder lalu aksi utama di kanan). HP: lebar penuh dengan margin 16px.
--}}
<dialog
    x-data="adminModal(@js($name), @js((bool) $show), @js((bool) $guard))"
    x-on:open-modal.window="$event.detail === name && open()"
    x-on:close-modal.window="$event.detail === name && close()"
    x-on:cancel.prevent="requestClose()"
    x-on:click="$event.target === $el && requestClose()"
    x-on:input="markDirty($event)"
    x-on:change="markDirty($event)"
    x-on:close="restoreFocus()"
    data-modal="{{ $name }}"@if ($show) data-open-on-load @endif
    aria-labelledby="modal-{{ $name }}-title"
    @if ($description) aria-describedby="modal-{{ $name }}-desc" @endif
    {{ $attributes->class(['app-modal m-auto max-h-[90dvh] w-[calc(100%-2rem)] overflow-hidden rounded-box border border-line bg-panel p-0 text-ink shadow-float md:max-h-[85dvh]', $width]) }}
>
    <div class="flex max-h-[inherit] flex-col">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-line-soft px-4 py-3.5 md:px-5">
            <div class="min-w-0">
                <h2 id="modal-{{ $name }}-title" class="text-body font-semibold text-ink">{{ $heading ?? $title }}</h2>
                @if ($description)
                    <p id="modal-{{ $name }}-desc" class="mt-0.5 text-caption text-ink-2">{{ $description }}</p>
                @endif
            </div>
            <button type="button" x-on:click="requestClose()" class="-my-1.5 -mr-2 flex size-11 shrink-0 items-center justify-center rounded-control text-ink-3 hover:bg-subtle hover:text-ink md:size-9" aria-label="Tutup">
                <x-heroicon-o-x-mark class="size-5" />
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 text-ui text-ink-2 md:px-5">
            {{ $slot }}
        </div>

        {{-- Konfirmasi buang perubahan (menggantikan kaki selama tampil). --}}
        <div x-cloak x-show="confirming" class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-line bg-wait-bg px-4 py-3 md:px-5" role="alertdialog" aria-live="assertive" aria-label="Buang perubahan?">
            <p class="text-caption font-medium text-ink">Buang perubahan? Isian yang belum disimpan akan hilang.</p>
            <div class="flex gap-2">
                <x-button variant="secondary" x-on:click="confirming = false" x-ref="keep">Kembali mengisi</x-button>
                <x-button variant="danger-solid" x-on:click="discard()">Buang</x-button>
            </div>
        </div>

        @isset($footer)
            <footer x-show="! confirming" class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-line-soft bg-panel px-4 py-3 md:px-5">{{ $footer }}</footer>
        @endisset
    </div>
</dialog>
