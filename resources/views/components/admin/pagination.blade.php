@props([
    'paginator',
    // Kata benda untuk "Menampilkan 1 sampai 20 dari 45 kelompok".
    'noun' => 'data',
])

{{-- Paginasi seragam: rentang yang tampil + Sebelumnya/Berikutnya (partials.pagination). --}}
@if ($paginator->total() > 0)
    <div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3') }}>
        <p class="text-caption text-ink-muted tabular-nums">
            Menampilkan {{ \App\Support\Format::number($paginator->firstItem()) }} sampai {{ \App\Support\Format::number($paginator->lastItem()) }} dari {{ \App\Support\Format::number($paginator->total()) }} {{ $noun }}
        </p>
        @if ($paginator->hasPages())
            <div class="flex gap-2">
                @if ($paginator->onFirstPage())
                    <x-button variant="secondary" disabled>Sebelumnya</x-button>
                @else
                    <x-button variant="secondary" :href="$paginator->previousPageUrl()" rel="prev">Sebelumnya</x-button>
                @endif
                @if ($paginator->hasMorePages())
                    <x-button variant="secondary" :href="$paginator->nextPageUrl()" rel="next">Berikutnya</x-button>
                @else
                    <x-button variant="secondary" disabled>Berikutnya</x-button>
                @endif
            </div>
        @endif
    </div>
@endif
