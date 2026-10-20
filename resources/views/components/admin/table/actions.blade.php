@props([
    // Nama baris untuk label tombol (mis. nama kelompok).
    'for' => null,
])

{{--
    Kolom aksi: tombol "…" yang membuka menu (isi slot: x-dropdown.item), rata kanan pada tombolnya. relative z-10 agar
    bisa diklik di dalam baris yang bisa diklik (openRowLink mengabaikan klik pada tombol).
    Kartu (< 768px): baris paling bawah selebar kartu (garis pemisah dari tepi ke tepi, juga di kartu bertautan yang memberi
    ruang chevron pr-10; max-md:w-auto menimpa w-12;
    tanpa itu sel tetap 48px sehingga garis pendek, tombol di kiri, dan menu keluar layar di kiri).
    Slot "phone" (opsional): tombol aksi yang langsung terlihat di HP (< 640px), menggantikan menu titik tiga.
--}}
<td {{ $attributes->class('relative z-10 w-12 px-2 text-right focus-within:z-20 max-md:col-span-2 max-md:-mx-4 max-md:mt-3 max-md:flex max-md:w-auto max-md:justify-end max-md:border-t max-md:border-line max-md:px-4 max-md:pt-3 max-md:[[data-row-href]_&]:-mr-10') }}>
    @isset($phone)
        <div class="flex w-full flex-wrap items-center gap-2 sm:hidden" data-phone-actions>{{ $phone }}</div>
    @endisset
    <x-dropdown width="w-56" @class(['max-sm:hidden' => isset($phone)])>
        <x-slot:trigger>
            <button type="button" x-bind:aria-expanded="open" aria-haspopup="menu" class="inline-flex size-11 items-center justify-center rounded-control text-ink-3 transition-colors hover:bg-subtle hover:text-ink md:size-8" aria-label="Tindakan{{ $for ? ' untuk '.$for : '' }}">
                <x-heroicon-o-ellipsis-vertical class="size-5" />
            </button>
        </x-slot:trigger>
        {{ $slot }}
    </x-dropdown>
</td>
