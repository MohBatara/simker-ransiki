@props([
    'name',
    // Nama instansi/OPD di bawah sapaan (opsional).
    'subtitle' => null,
])

@php
    $now = now(config('simker.reports.timezone'));
    $greeting = match (true) {
        $now->hour < 11 => 'Selamat pagi',
        $now->hour < 15 => 'Selamat siang',
        $now->hour < 18 => 'Selamat sore',
        default => 'Selamat malam',
    };
    $firstName = \Illuminate\Support\Str::of($name)->before(' ')->toString() ?: $name;
@endphp

{{--
    Kartu sapaan dasbor (kecil, forest): sapaan menurut jam WIT, tanggal hari ini, slot kanan untuk pemilih periode.
    Teks putih 14,9:1 dan forest-soft 8,4:1 di atas forest. Token forest di area aplikasi: pengecualian Bagian 7.
--}}
<section {{ $attributes->class('on-dark flex flex-col gap-4 rounded-tile bg-forest px-5 py-6 text-paper md:flex-row md:items-center md:justify-between md:px-6') }}>
    <div class="min-w-0">
        <p class="text-caption text-forest-soft"><time datetime="{{ $now->toDateString() }}">{{ \App\Support\Format::dayDate($now) }}</time>@if ($subtitle) <span aria-hidden="true">·</span> {{ $subtitle }}@endif</p>
        <p class="mt-1 text-page-title font-semibold">{{ $greeting }}, {{ $firstName }}</p>
    </div>
    @if ($slot->isNotEmpty())
        <div class="shrink-0 md:w-56">{{ $slot }}</div>
    @endif
</section>
