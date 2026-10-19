@props([
    // Nama baris untuk label tombol (mis. nama kelompok).
    'for' => null,
])

{{--
    Kolom aksi: tombol "…" yang membuka menu (isi slot: x-dropdown.item). relative z-10 agar bisa diklik di dalam baris
    yang bisa diklik (openRowLink mengabaikan klik pada tombol). Di kartu HP menjadi baris paling bawah, rata kanan.
--}}
<td {{ $attributes->class('relative z-10 w-12 px-2 text-right focus-within:z-20 max-md:col-span-2 max-md:mt-2 max-md:flex max-md:justify-end max-md:border-t max-md:border-line max-md:px-0 max-md:pt-2') }}>
    <x-dropdown width="w-56">
        <x-slot:trigger>
            <button type="button" x-bind:aria-expanded="open" aria-haspopup="menu" class="inline-flex size-11 items-center justify-center rounded-control text-ink-3 transition-colors hover:bg-subtle hover:text-ink md:size-8" aria-label="Tindakan{{ $for ? ' untuk '.$for : '' }}">
                <x-heroicon-o-ellipsis-vertical class="size-5" />
            </button>
        </x-slot:trigger>
        {{ $slot }}
    </x-dropdown>
</td>
