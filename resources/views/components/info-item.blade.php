@props([
    'label',
    // Ikon Heroicons dalam lingkaran brand-tint: hanya untuk kartu informasi (Bagian 7).
    'icon',
])

{{-- Satu fakta di kartu informasi: ikon dalam lingkaran, label kecil, lalu nilainya. --}}
<div {{ $attributes->class('flex items-start gap-3') }}>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand" aria-hidden="true">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" />
    </span>
    <div class="min-w-0">
        <p class="text-caption text-ink-muted">{{ $label }}</p>
        <div class="break-words text-ui text-ink">{{ $slot }}</div>
    </div>
</div>
