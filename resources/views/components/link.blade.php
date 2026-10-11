@props([
    // Tanpa href menjadi <button type="button"> (mis. "Pakai jumlah ini").
    'href' => null,
    // true untuk tautan di dalam kalimat: tanpa tinggi minimum.
    'inline' => false,
    'muted' => false,
])

{{--
    Tautan teks. Yang berdiri sendiri (bukan di dalam kalimat) setinggi 44px di HP agar mudah disentuh.
--}}
@php
    $classes = [
        'rounded-control font-semibold transition-colors',
        'inline-flex min-h-11 items-center md:min-h-0' => ! $inline,
        // Di dalam kalimat warna saja tidak cukup membedakan tautan dari teks (kontras < 3:1), jadi bergaris bawah.
        'underline underline-offset-2' => $inline,
        'text-brand hover:text-brand-hover' => ! $muted,
        'text-ink-soft hover:text-ink' => $muted,
        // Di pita/banner forest warna brand tidak terbaca; putih bergaris bawah.
        'md:in-[.app-band]:text-paper md:in-[.app-band]:underline in-[.app-band-photo]:text-paper in-[.app-band-photo]:underline',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
