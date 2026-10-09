@if ($paginator->hasPages())
    <nav aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-caption text-ink-muted tabular-nums">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
        </p>
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
    </nav>
@endif
