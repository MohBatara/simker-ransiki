{{--
    Alat kepala halaman area aplikasi (tablet/desktop): tombol cari (membuka dialog pencarian umum, juga Ctrl/Cmd + K;
    Admin dan OPD) + lonceng bertitik merah. Ukuran tombol cari sama dengan lonceng.
--}}
@if ($searchUrl)
    <button type="button" x-data x-on:click="$dispatch('open-modal', 'cari-global')" class="flex size-11 shrink-0 items-center justify-center rounded-control border border-line text-ink-soft transition-colors hover:bg-subtle hover:text-ink md:size-(--control-h)" aria-label="Cari kelompok, ketua, kampung, atau produk (Ctrl atau Cmd + K)" title="Cari (Ctrl/Cmd + K)" data-search-open>
        <x-heroicon-o-magnifying-glass class="size-5 app:md:size-[18px]" />
    </button>
@endif
@if ($notificationsUrl)
    <x-notification-bell :href="$notificationsUrl" :count="$unreadNotifications" :active="request()->routeIs('notifications.*')" dot data-bell="desktop" />
@endif
