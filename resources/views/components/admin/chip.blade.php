@props([
    // Ikon Heroicons kecil (12px) di depan teks, mis. jenis kelembagaan atau kategori.
    'icon' => null,
])

{{-- Chip kategori/jenis di tabel dan legenda: bingkai line, ikon 12px, teks 12px. --}}
<span {{ $attributes->class('inline-flex h-6 max-w-full items-center gap-1 whitespace-nowrap rounded-pill border border-line bg-panel px-2 text-label text-ink-2') }}>
    @if ($icon)
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-3 shrink-0 text-ink-3" aria-hidden="true" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</span>
