@props([
    'title',
    'description' => null,
    'icon' => 'inbox',
    // Di dalam kartu: tanpa bingkai sendiri.
    'bare' => false,
])

{{-- Keadaan kosong seragam: ikon, judul, satu kalimat, tombol aksi (slot). --}}
<div {{ $attributes->class(['flex flex-col items-center bg-panel px-4 py-12 text-center md:px-6', 'rounded-box border border-line' => ! $bare]) }}>
    <span class="flex size-12 items-center justify-center rounded-box bg-brand-50 text-brand" aria-hidden="true">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" />
    </span>
    <p class="mt-4 text-card-head font-semibold text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-md text-caption text-ink-2">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
