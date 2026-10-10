@props([
    'href',
    'count' => 0,
])

{{-- Lonceng notifikasi dengan angka belum dibaca (maksimal "9+"). --}}
@php
    $label = $count > 0 ? "Notifikasi, {$count} belum dibaca" : 'Notifikasi';
@endphp

<a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class('relative flex size-11 shrink-0 items-center justify-center rounded-control text-ink-soft transition-colors hover:bg-subtle hover:text-ink md:size-10') }}>
    <x-heroicon-o-bell class="size-5" />
    @if ($count > 0)
        <x-notification-count :count="$count" class="absolute right-1 top-1" />
    @endif
</a>
