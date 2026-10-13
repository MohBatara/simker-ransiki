@props([
    'href',
    // Lebar penuh (tombol di bawah daftar di HP): teks di kiri, lingkaran panah di kanan.
    'full' => false,
])

{{-- Tombol pil forest dengan lingkaran panah putih (Baca profil lengkap, Lihat semua produk, Hubungi Kantor Distrik). --}}
<a href="{{ $href }}" {{ $attributes->class([
    'group inline-flex min-h-11 items-center gap-3 rounded-full bg-forest py-1.5 pr-1.5 pl-5 font-semibold text-paper transition-colors hover:bg-brand-hover',
    'w-full justify-between' => $full,
]) }}>
    {{ $slot }}
    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-paper text-forest" aria-hidden="true">
        <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" />
    </span>
</a>
