@props([
    // Baris catatan (string atau list of string) yang tampil sebagai tooltip saat ikon disorot/difokuskan.
    'notes' => [],
    'label' => 'Keterangan',
    'id' => null,
])

{{--
    Ikon info (i) di kepala kartu: catatan cara menghitung sebagai tooltip (hover/fokus), bukan teks panjang di badan kartu.
    Tombol 44px di HP, 32px mulai tablet.
--}}
@php
    $id ??= 'info-'.\Illuminate\Support\Str::random(6);
    $notes = (array) $notes;
@endphp

<span {{ $attributes->class('group relative inline-flex') }}>
    <button type="button" class="flex size-11 items-center justify-center rounded-control text-ink-3 transition-colors hover:bg-subtle hover:text-ink md:size-8" aria-label="{{ $label }}" aria-describedby="{{ $id }}">
        <x-heroicon-o-information-circle class="size-5" />
    </button>
    <span id="{{ $id }}" role="tooltip" class="invisible absolute right-0 top-full z-20 mt-1 w-72 rounded-card bg-ink px-3 py-2 text-caption text-paper opacity-0 shadow-float transition-opacity group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
        @foreach ($notes as $note)
            <span class="block">{{ $note }}</span>
        @endforeach
    </span>
</span>
