{{-- Paginasi bernomor website publik: Sebelumnya, nomor halaman, Berikutnya. Tombol 44px; halaman aktif terisi brand. --}}
@if ($paginator->hasPages())
    @php
        $base = 'inline-flex h-11 min-w-11 items-center justify-center gap-1 rounded-control px-3 text-ui font-semibold transition-colors';
        $link = $base.' border border-line-strong bg-paper text-ink hover:border-brand hover:text-brand';
        $disabled = $base.' border border-line text-ink-muted';
    @endphp
    <nav aria-label="Halaman" class="flex flex-wrap items-center justify-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="{{ $disabled }}" aria-disabled="true"><x-heroicon-o-chevron-left class="size-4" aria-hidden="true" />Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}"><x-heroicon-o-chevron-left class="size-4" aria-hidden="true" />Sebelumnya</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="inline-flex h-11 min-w-8 items-center justify-center text-ink-muted" aria-hidden="true">…</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $base }} bg-brand text-paper tabular-nums" aria-current="page"><span class="sr-only">Halaman </span>{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $link }} tabular-nums"><span class="sr-only">Halaman </span>{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}">Berikutnya<x-heroicon-o-chevron-right class="size-4" aria-hidden="true" /></a>
        @else
            <span class="{{ $disabled }}" aria-disabled="true">Berikutnya<x-heroicon-o-chevron-right class="size-4" aria-hidden="true" /></span>
        @endif
    </nav>
@endif
