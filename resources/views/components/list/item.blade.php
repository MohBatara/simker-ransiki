@aware([
    'flush' => false,
])

@props([
    'href' => null,
    'chevron' => null,
])

{{--
    Satu baris daftar: min 44px, hover subtle, chevron di kanan bila bisa diklik.
    Tanpa href: wadah biasa (mis. baris yang berisi tautan dan tombolnya sendiri).
--}}
@php
    $chevron ??= (bool) $href;
    $padding = $flush ? 'px-4 md:px-5' : 'px-2';
@endphp

<li>
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->class(['flex min-h-11 items-center gap-3 py-3 transition-colors hover:bg-subtle', $padding, 'rounded-control' => ! $flush]) }}>
            {{ $slot }}
            @if ($chevron)
                <x-heroicon-o-chevron-right class="size-4 shrink-0 text-ink-muted" />
            @endif
        </a>
    @else
        <div {{ $attributes->class(['flex min-h-11 items-center gap-3 py-3', $padding]) }}>
            {{ $slot }}
        </div>
    @endif
</li>
