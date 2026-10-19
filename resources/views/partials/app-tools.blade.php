{{-- Alat kepala halaman area aplikasi (tablet/desktop): cari ringkas global (Admin, OPD) + lonceng bertitik merah. --}}
@if ($searchUrl)
    <form method="GET" action="{{ $searchUrl }}" role="search" class="w-60">
        <x-search-input id="cari-global" label="Cari kelompok, ketua, kampung, atau produk" placeholder="Cari" :value="request()->routeIs('*.search') ? request('q') : null" />
    </form>
@endif
@if ($notificationsUrl)
    <x-notification-bell :href="$notificationsUrl" :count="$unreadNotifications" :active="request()->routeIs('notifications.*')" dot data-bell="desktop" />
@endif
