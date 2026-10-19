{{--
    Dialog pencarian umum area aplikasi (Admin, OPD): kelompok menurut nama, ketua, kampung; produk menurut nama. Dibuka
    tombol cari di kepala halaman / header HP atau Ctrl/Cmd + K; Enter membuka halaman hasil /{area}/cari (visibleTo).
--}}
<div x-data x-on:keydown.window.ctrl.k.prevent="$dispatch('open-modal', 'cari-global')" x-on:keydown.window.meta.k.prevent="$dispatch('open-modal', 'cari-global')" hidden></div>
<x-modal name="cari-global" title="Cari" class="md:mt-[12vh] md:mb-auto">
    <form novalidate method="GET" action="{{ $searchUrl }}" role="search" class="space-y-3">
        <x-search-input id="cari-global" label="Cari kelompok, ketua, kampung, atau produk" placeholder="Nama kelompok, ketua, kampung, atau produk" :value="request()->routeIs('*.search') ? request('q') : null" autofocus />
        <p class="flex flex-wrap items-center justify-between gap-2 text-label text-ink-3">
            <span>Tekan Enter untuk mencari.</span>
            <span class="max-md:hidden"><kbd class="rounded-pill border border-line px-1.5 font-sans">Ctrl</kbd> atau <kbd class="rounded-pill border border-line px-1.5 font-sans">Cmd</kbd> + <kbd class="rounded-pill border border-line px-1.5 font-sans">K</kbd> membuka pencarian dari halaman mana pun.</span>
        </p>
    </form>
</x-modal>
