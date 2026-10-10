@props([
    'href',
    'count' => 0,
    // Tampil aktif di halaman Notifikasi.
    'active' => false,
])

{{-- Lonceng notifikasi dengan angka belum dibaca (maksimal "9+"). --}}
@php
    $label = $count > 0 ? "Notifikasi, {$count} belum dibaca" : 'Notifikasi';
@endphp

<a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" @if ($active) aria-current="page" @endif {{ $attributes->class([
    'relative flex size-11 shrink-0 items-center justify-center rounded-control transition-colors md:size-10',
    'bg-brand-tint text-brand' => $active,
    'text-ink-soft hover:bg-subtle hover:text-ink' => ! $active,
]) }}>
    <x-heroicon-o-bell class="size-5" />
    @if ($count > 0)
        <x-notification-count :count="$count" class="absolute right-1 top-1" />
    @endif
</a>
