@props([
    // Daftar nama (mis. bidang usaha): tampil yang pertama + chip "+n", daftar lengkap di tooltip.
    'items' => [],
])

@php $items = collect($items)->filter()->values(); @endphp

@if ($items->isEmpty())
    {{ \App\Support\Format::orEmpty(null) }}
@else
    <span {{ $attributes->class('inline-flex max-w-full items-center gap-1.5 md:whitespace-nowrap') }} title="{{ $items->join(', ') }}">
        <span class="truncate">{{ $items->first() }}</span>
        @if ($items->count() > 1)
            <span class="inline-flex h-5 shrink-0 items-center rounded-pill border border-line px-1.5 text-label text-ink-2 tabular-nums" aria-label="dan {{ $items->count() - 1 }} lainnya: {{ $items->slice(1)->join(', ') }}">+{{ $items->count() - 1 }}</span>
        @endif
    </span>
@endif
