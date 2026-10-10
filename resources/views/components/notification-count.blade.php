@props([
    'count' => 0,
])

{{-- Angka belum dibaca: pil kecil warna brand. Dibacakan lewat label induknya. --}}
<span aria-hidden="true" {{ $attributes->class('inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1 text-caption font-semibold leading-none tabular-nums text-paper') }}>{{ $count > 9 ? '9+' : $count }}</span>
