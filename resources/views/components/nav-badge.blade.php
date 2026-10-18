@props([
    'count' => 0,
])

{{--
    Jumlah yang menunggu di menu Kelompok/Laporan (sidebar, navigasi bawah HP): pil kecil sun berteks ink (9,0:1).
    Pengecualian token sun di area aplikasi (CLAUDE.md Bagian 6). Dibacakan lewat label induknya; tidak tampil bila 0.
--}}
@if ($count > 0)
    <span aria-hidden="true" {{ $attributes->class('inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-sun px-1 text-caption font-semibold leading-none tabular-nums text-ink') }}>{{ $count > 99 ? '99+' : $count }}</span>
@endif
