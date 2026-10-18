@props([
    'title',
    'description' => null,
    'icon' => 'inbox',
])

{{-- Keadaan kosong seragam: ikon, judul, satu kalimat, tombol aksi (slot). --}}
<div {{ $attributes->class('flex flex-col items-center rounded-tile border border-line bg-paper px-4 py-12 text-center md:px-6') }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-subtle text-ink-soft" aria-hidden="true">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" />
    </span>
    <p class="mt-4 text-body font-semibold text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-md text-ui text-ink-soft">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
