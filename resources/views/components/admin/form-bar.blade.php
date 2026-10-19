@props([
    // Tujuan tombol Batal (null = tanpa Batal).
    'cancel' => null,
    'submit' => 'Simpan',
    'processing' => 'Menyimpan…',
    // Id form bila bar berada di luar elemen <form>.
    'form' => null,
    // Form dibuka ulang dengan isian lama (validasi gagal): perubahan belum tersimpan sejak awal.
    'dirty' => null,
])

@php
    $dirty ??= isset($errors) && $errors->any();
@endphp

{{--
    Bar aksi form yang menempel di bawah layar (semua ukuran): Batal (sekunder) + Simpan (utama), dan info
    "Perubahan belum disimpan" setelah isian berubah. Alpine "formGuard" (resources/js/form-guard.js) memperingatkan saat
    meninggalkan halaman dengan perubahan yang belum disimpan. Slot = tombol tambahan di kiri tombol Batal.
--}}
<div
    x-data="formGuard(@js($dirty))"
    @if ($form) data-form="{{ $form }}" @endif
    {{ $attributes->class('sticky bottom-[var(--app-bottom,0px)] z-10 -mx-4 mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line bg-panel px-4 pt-3 pb-[calc(0.75rem+var(--app-safe-bottom,0px))] md:bottom-0 md:-mx-7 md:rounded-t-box md:border-x md:px-7 lg:group-data-[nav]/form:mx-0 lg:group-data-[nav]/form:px-5 print:hidden') }}
>
    <p class="flex min-w-0 flex-1 items-center gap-2 text-ui text-ink-soft max-md:w-full max-md:text-caption" role="status">
        <span x-cloak x-show="dirty" class="inline-flex items-center gap-2"><span class="size-2 shrink-0 rounded-full bg-wait" aria-hidden="true"></span>Perubahan belum disimpan</span>
    </p>
    <div class="flex flex-wrap items-center gap-2 max-md:w-full max-md:*:flex-1 md:ml-auto">
        {{ $slot }}
        @if ($cancel)
            <x-button variant="secondary" :href="$cancel">Batal</x-button>
        @endif
        <x-button type="submit" :form="$form" :processing="$processing">{{ $submit }}</x-button>
    </div>
</div>
