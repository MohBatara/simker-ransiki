@props([
    // Di latar gelap (panel halaman masuk).
    'inverse' => false,
])

{{-- Placeholder sampai logo resmi tersedia; ganti isi komponen ini dengan <img> logo. --}}
<span {{ $attributes->class([
    'flex size-8 shrink-0 items-center justify-center rounded-control border text-caption font-semibold',
    'border-brand text-brand' => ! $inverse,
    'border-paper text-paper' => $inverse,
]) }} aria-hidden="true">SK</span>
