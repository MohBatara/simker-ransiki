@props([
    'value',
    // Tooltip memuat jam (mis. waktu login, waktu perubahan).
    'time' => false,
])

{{-- Tanggal ringkas di tabel: "4 Sep 2026", tidak patah baris; tanggal lengkap di tooltip. --}}
@if ($value)
    @php $date = \Illuminate\Support\Carbon::parse($value); @endphp
    <time datetime="{{ $date->toIso8601String() }}" title="{{ $time ? \App\Support\Format::dateTime($date).' WIT' : \App\Support\Format::date($date) }}" {{ $attributes->class('whitespace-nowrap tabular-nums') }}>{{ \App\Support\Format::dateShort($date) }}</time>
@else
    <span {{ $attributes->class('text-ink-muted') }}>{{ \App\Support\Format::EMPTY }}</span>
@endif
