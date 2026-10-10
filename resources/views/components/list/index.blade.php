@props([
    // flush: daftar di kartu tanpa padding (baris selebar kartu); inset: di dalam kartu berpadding.
    'flush' => false,
])

{{-- Daftar baris (pakai x-list.item). Baris dengan href bisa diklik seluruhnya. --}}
<ul {{ $attributes->class(['divide-y divide-line', '-mx-2' => ! $flush]) }}>
    {{ $slot }}
</ul>
