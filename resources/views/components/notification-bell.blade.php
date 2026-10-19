@props([
    'href',
    'count' => 0,
    // Tampil aktif di halaman Notifikasi.
    'active' => false,
    // Kepala halaman area aplikasi: titik merah (bukan angka) bila ada yang belum dibaca.
    'dot' => false,
])

{{-- Lonceng notifikasi dengan angka belum dibaca (maksimal "9+") atau titik merah (dot). --}}
@php
    $label = $count > 0 ? "Notifikasi, {$count} belum dibaca" : 'Notifikasi';
@endphp

<a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" @if ($active) aria-current="page" @endif {{ $attributes->class([
    'relative flex size-11 shrink-0 items-center justify-center rounded-control transition-colors md:size-(--control-h)',
    'border border-line' => $dot,
    'bg-brand-tint text-brand' => $active,
    'text-ink-soft hover:bg-subtle hover:text-ink' => ! $active,
]) }}>
    <x-heroicon-o-bell class="size-5 app:md:size-[18px]" />
    @if ($count > 0 && $dot)
        <span class="absolute right-2 top-2 size-2 rounded-full bg-fix ring-2 ring-panel" aria-hidden="true"></span>
    @elseif ($count > 0)
        <x-notification-count :count="$count" class="absolute right-1 top-1" />
    @endif
</a>
