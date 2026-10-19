@props([
    'name',
    // Teks kecil ink-3 di bawah nama (mis. ketua atau kampung); kosong = tidak tampil.
    'subtitle' => null,
])

{{-- Sel nama di tabel: avatar inisial (tablet/desktop; di HP kartu sempit) + nama maksimal 2 baris (nama lengkap di title) + keterangan kecil. --}}
<span {{ $attributes->class('flex min-w-0 items-center gap-3') }}>
    <x-avatar :name="$name" size="sm" class="max-md:hidden" />
    <span class="min-w-0">
        <span class="line-clamp-2 md:min-w-40" title="{{ $name }}">{{ $name }}</span>
        @if (filled($subtitle))
            <span class="block truncate text-label font-normal text-ink-3">{{ $subtitle }}</span>
        @endif
    </span>
</span>
