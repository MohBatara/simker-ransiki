@props([
    'paginator',
    // Kata benda untuk "Menampilkan 1 sampai 20 dari 45 kelompok".
    'noun' => 'data',
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    // Halaman yang ditampilkan: pertama, terakhir, dan dua di sekitar halaman aktif; sisanya "…".
    $pages = collect([1, $last, $current - 1, $current, $current + 1])->filter(fn ($page) => $page >= 1 && $page <= $last)->unique()->sort()->values();
    $box = 'inline-flex size-11 items-center justify-center rounded-control border text-caption font-medium tabular-nums transition-colors md:size-8';
@endphp

{{-- Paginasi kecil: rentang yang tampil + tombol halaman berbingkai (sebelumnya, nomor, berikutnya). --}}
@if ($paginator->total() > 0)
    <div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3') }}>
        <p class="text-caption text-ink-3 tabular-nums">
            Menampilkan {{ \App\Support\Format::number($paginator->firstItem()) }} sampai {{ \App\Support\Format::number($paginator->lastItem()) }} dari {{ \App\Support\Format::number($paginator->total()) }} {{ $noun }}
        </p>
        @if ($paginator->hasPages())
            <nav aria-label="Halaman" class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $box }} border-line text-ink-faint" aria-hidden="true"><x-heroicon-o-chevron-left class="size-4" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $box }} border-line text-ink-2 hover:bg-subtle" aria-label="Sebelumnya"><x-heroicon-o-chevron-left class="size-4" /></a>
                @endif
                @foreach ($pages as $index => $page)
                    @if ($index > 0 && $page - $pages[$index - 1] > 1)
                        <span class="px-1 text-caption text-ink-3" aria-hidden="true">…</span>
                    @endif
                    @if ($page === $current)
                        <span class="{{ $box }} border-brand bg-brand-50 text-brand" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="{{ $box }} border-line text-ink-2 hover:bg-subtle max-sm:hidden" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $box }} border-line text-ink-2 hover:bg-subtle" aria-label="Berikutnya"><x-heroicon-o-chevron-right class="size-4" /></a>
                @else
                    <span class="{{ $box }} border-line text-ink-faint" aria-hidden="true"><x-heroicon-o-chevron-right class="size-4" /></span>
                @endif
            </nav>
        @endif
    </div>
@endif
