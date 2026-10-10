{{--
    Tombol halaman error: "Kembali" (halaman sebelumnya) dan tujuan utama (dasbor bila masih login,
    beranda atau halaman masuk bila tidak). Variabel opsional: $reload (tampilkan "Muat ulang"),
    $login (tujuan utama = halaman masuk untuk tamu).
--}}
@php
    $user = rescue(fn () => auth()->user(), null, false);
    [$url, $label] = match (true) {
        (bool) $user => [route($user->role->dashboardRoute()), 'Ke dasbor'],
        $login ?? false => [route('login'), 'Masuk lagi'],
        default => [url('/'), 'Ke beranda'],
    };
@endphp

<div class="mt-5 flex flex-wrap gap-2 max-sm:*:flex-1" x-data>
    @if ($reload ?? false)
        <x-button variant="secondary" icon="arrow-path" x-on:click="window.location.reload()">Muat ulang</x-button>
    @else
        <x-button variant="secondary" icon="arrow-left" x-on:click="history.length > 1 ? history.back() : window.location.assign(@js($url))">Kembali</x-button>
    @endif
    <x-button :href="$url">{{ $label }}</x-button>
</div>
