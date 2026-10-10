@props([
    'numeric' => false,
    'optional' => false,
])

{{-- Kolom angka: padding kiri/kanan ditulis terpisah (bukan px + pr) agar tepi kanannya selalu sama dengan sel. --}}
<th scope="col" {{ $attributes->class([
    'h-10 whitespace-nowrap align-middle text-caption font-semibold text-ink-soft',
    'pl-4 pr-8 text-right' => $numeric,
    'px-4 text-left' => ! $numeric,
    'md:max-xl:hidden' => $optional,
]) }}>{{ $slot }}</th>
