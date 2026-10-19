@props([
    'title',
    'description' => null,
    // Keterangan kecil di atas judul, mis. "Dasbor" bila judulnya nama kelompok.
    'eyebrow' => null,
    'back' => null,
    // Remah halaman bertingkat (desktop). Di HP tetap tombol kembali ke $back.
    'breadcrumbs' => null,
    // Judul header atas HP bila berbeda dari judul halaman (mis. "Detail kelompok").
    'phoneTitle' => null,
    // Item: ['label', 'href', 'icon' (Heroicons outline), 'short' (label ringkas), 'active', 'primary' (tampil di navigasi bawah HP)]
    // Kosongkan agar diambil otomatis dari peran pengguna yang login (App\Support\Navigation).
    'navigation' => null,
    'notificationsUrl' => null,
    'userName' => null,
    'userRole' => null,
    // Mode form di HP: navigasi bawah disembunyikan, header berisi tombol kembali + judul.
    'focus' => false,
    // Menu bertab Admin/OPD (App\Support\Navigation::sections): kunci menu + kunci tab aktif. Tab tampil di bawah
    // header; remah bawaan "Menu › Tab". $tabUrls menimpa URL tab tertentu (mis. filter Statistik ikut pindah tab).
    'section' => null,
    'tab' => null,
    'tabUrls' => [],
    // false: tanpa header halaman (dasbor Admin/OPD: kartu sapaan di slot "lead" menjadi elemen teratas, h1 untuk pembaca layar).
    'header' => true,
    // Dengan header false: halaman merender h1 sendiri (mis. kepala detail kelompok), jadi tanpa h1 pembaca layar.
    'ownHeading' => false,
])

@php
    $user = auth()->user();
    $userName ??= $user?->name;
    // Peran singkat di blok pengguna sidebar: nama singkat OPD untuk petugas OPD.
    $userRole ??= $user?->isOpd() ? 'OPD · '.($user->opd?->short_name ?: $user->opd?->name) : $user?->role?->label();
    $navigation ??= $user ? \App\Support\Navigation::for($user) : [];
    $notificationsUrl ??= Route::has('notifications.index') ? route('notifications.index') : null;
    // Dari view composer (AppServiceProvider); 0 bila tidak ada.
    $unreadNotifications ??= 0;
    $accountUrl = $user && Route::has('account.password.edit') ? route('account.password.edit') : null;
    // Cari global hanya untuk Admin dan OPD (kelompok hanya punya satu kelompok).
    $searchUrl = $user && ! $user->isKelompok() && Route::has($user->role->area().'.search') ? route($user->role->area().'.search') : null;
    $back ??= $focus ? url()->previous() : null;

    // Alat kepala halaman (tablet/desktop): cari ringkas (Admin, OPD) + lonceng bertitik merah.
    $tools = trim(view('partials.app-tools', [
        'searchUrl' => $searchUrl,
        'notificationsUrl' => $notificationsUrl,
        'unreadNotifications' => $unreadNotifications,
    ])->render());

    $sectionTabs = $section && $user ? \App\Support\Navigation::tabs($user, $section) : null;
    $headTitle = $title;
    if ($sectionTabs) {
        // Halaman utama bertab: cukup judul dan subjudul (remah hanya di halaman detail dan form, diisi halaman itu).
        $headTitle = $title === $sectionTabs['label'] ? $title : $title.' · '.$sectionTabs['label'];
    }

    $navigation = collect($navigation);
    $sidebarGroups = $navigation
        ->reject(fn ($item) => ($item['route'] ?? null) === 'notifications.index' || ! ($item['sidebar'] ?? true))
        ->groupBy(fn ($item) => $item['group'] ?? 1);
    if ($navigation->count() <= 5) {
        $bottomNav = $navigation;
        $moreNav = collect();
    } else {
        $flagged = $navigation->where('primary', true);
        $bottomNav = ($flagged->isNotEmpty() ? $flagged : $navigation)->take(4);
        $moreNav = $navigation->diffKeys($bottomNav);
    }
    $bottomColumns = match ($bottomNav->count() + ($moreNav->isNotEmpty() ? 1 : 0)) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
        default => 'grid-cols-5',
    };
@endphp

<!DOCTYPE html>
{{-- scroll-padding: elemen yang difokuskan tidak tertutup header atas atau bar aksi yang menempel. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['scroll-pt-20 md:scroll-pt-6', 'max-md:scroll-pb-36' => $focus, 'max-md:scroll-pb-20' => ! $focus])>
<head>
    @include('partials.head', ['title' => $headTitle, 'noindex' => true])
</head>
{{-- --app-bottom: tinggi area tetap di bawah layar HP, dipakai x-form-actions sticky. --}}
<body @class([
    'theme-app min-h-dvh bg-canvas font-sans text-ui text-ink antialiased',
    '[--app-bottom:calc(4rem+env(safe-area-inset-bottom))] [--app-safe-bottom:0px]' => ! $focus,
    '[--app-bottom:0px] [--app-safe-bottom:env(safe-area-inset-bottom)]' => $focus,
])>
    @include('partials.skip-link')
    {{--
        Sidebar menempel di latar canvas (tanpa latar putih): ikon + label ringkas di tablet (72px), penuh di desktop
        (240px). Atas: kartu logo; tengah: menu (40px, sudut 8px, aktif brand-50); bawah: profil yang membuka menu kecil.
    --}}
    <aside class="hidden md:fixed md:inset-y-0 md:left-0 md:z-20 md:flex md:w-[72px] md:flex-col md:bg-canvas xl:w-[240px]">
        <div class="shrink-0 px-2 pt-3 xl:px-3" title="SIM-KER Distrik Ransiki">
            <div class="flex flex-col items-center gap-1 rounded-box border border-line bg-panel p-2 xl:flex-row xl:gap-3 xl:p-3">
                <x-logo />
                <div class="hidden min-w-0 flex-1 leading-tight xl:block">
                    <span class="flex items-center justify-between gap-2 text-ui font-semibold text-ink">SIM-KER @include('partials.test-mode-pill')</span>
                    <span class="block text-label text-ink-3">Distrik Ransiki</span>
                </div>
                {{-- Tablet: hanya logo, jadi penanda mode uji ringkas di bawahnya. --}}
                @include('partials.test-mode-pill', ['class' => 'xl:hidden', 'short' => true])
                <span class="sr-only xl:hidden">SIM-KER Distrik Ransiki</span>
            </div>
        </div>

        {{-- Di tablet nav tidak di-scroll agar tooltip tidak terpotong oleh tepi sidebar. Grup kecil tanpa judul, dipisah garis. --}}
        <nav aria-label="Menu utama" class="flex-1 px-2 py-4 xl:overflow-y-auto xl:px-3">
            {{-- Notifikasi tidak di sidebar: lonceng ada di kepala halaman; Akun Saya ada di menu profil bawah. --}}
            @foreach ($sidebarGroups as $items)
                <ul @class(['space-y-1', 'mt-3 border-t border-line pt-3' => ! $loop->first])>
                    @foreach ($items as $item)
                        @php
                            $active = $item['active'] ?? false;
                            $navCount = $item['count'] ?? 0;
                        @endphp
                        <li>
                            <a
                                href="{{ $item['href'] }}"
                                aria-label="{{ $item['label'] }}{{ $navCount > 0 ? ', '.$navCount.' menunggu verifikasi' : '' }}"
                                @if ($active) aria-current="page" @endif
                                @class([
                                    'group relative flex flex-col items-center gap-1 rounded-control px-1 py-2 transition-colors xl:h-10 xl:flex-row xl:gap-3 xl:px-3 xl:py-0',
                                    // Aktif: kartu putih berbingkai + bayangan sangat tipis, garis kiri brand 3px.
                                    'border border-line bg-panel font-semibold text-brand shadow-xs before:absolute before:inset-y-2 before:-left-px before:w-[3px] before:rounded-r-full before:bg-brand' => $active,
                                    'border border-transparent text-ink-2 hover:bg-panel/60 hover:text-ink' => ! $active,
                                ])
                            >
                                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5 shrink-0 xl:size-[18px]" />
                                <span class="w-full truncate text-center text-micro xl:hidden" aria-hidden="true">{{ $item['short'] ?? $item['label'] }}</span>
                                <span class="hidden truncate text-ui xl:inline" aria-hidden="true">{{ $item['label'] }}</span>
                                <x-nav-badge :count="$navCount" class="absolute top-1 left-1/2 ml-1.5 xl:static xl:ml-auto" />
                                <span aria-hidden="true" class="pointer-events-none absolute left-full top-1/2 z-30 ml-2 -translate-y-1/2 whitespace-nowrap rounded-control bg-ink px-2 py-1 text-caption font-normal text-paper opacity-0 shadow-float transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 xl:hidden">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        {{-- Profil di bawah sidebar: avatar, nama, peran; diklik membuka menu kecil Akun saya / Keluar. --}}
        @if ($userName)
            <div class="shrink-0 px-2 pb-3 xl:px-3" data-sidebar-profile>
                <div x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" class="relative">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" class="flex w-full items-center gap-3 rounded-control p-2 text-left transition-colors hover:bg-panel/60 max-xl:justify-center">
                        <x-avatar :name="$userName" class="bg-brand-100! text-brand!" />
                        <span class="hidden min-w-0 flex-1 leading-tight xl:block">
                            <span class="block truncate text-ui font-semibold text-ink">{{ $userName }}</span>
                            @if ($userRole)
                                <span class="block truncate text-label text-ink-3">{{ $userRole }}</span>
                            @endif
                        </span>
                        <x-heroicon-o-chevron-up-down class="hidden size-4 shrink-0 text-ink-3 xl:block" aria-hidden="true" />
                        <span class="sr-only">Menu akun {{ $userName }}</span>
                    </button>
                    <div x-cloak x-show="open" x-transition.opacity.duration.100ms class="absolute bottom-full left-0 z-30 mb-1 w-56 rounded-card border border-line bg-panel py-1 shadow-float">
                        @if ($accountUrl)
                            <x-dropdown.item :href="$accountUrl" icon="user-circle">Akun saya</x-dropdown.item>
                        @endif
                        @if (Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}" data-logout data-no-processing>
                                @csrf
                                <x-dropdown.item type="submit" icon="arrow-right-start-on-rectangle">Keluar</x-dropdown.item>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </aside>

    <div class="md:pl-[72px] xl:pl-[240px]">
        {{-- Header atas tipis di HP --}}
        <header class="sticky top-0 z-20 border-b border-line bg-paper pt-[env(safe-area-inset-top)] md:hidden">
            <div class="flex h-14 items-center gap-1 px-4">
                @if ($back)
                    <a href="{{ $back }}" class="-ml-2 flex size-11 shrink-0 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Kembali">
                        <x-heroicon-o-arrow-left class="size-5" />
                    </a>
                @endif
                <p class="min-w-0 truncate text-ui font-semibold text-ink">{{ $phoneTitle ?? $title }}</p>
                @include('partials.test-mode-pill', ['class' => 'ml-1', 'short' => true])
                <span class="flex-1"></span>
                @unless ($focus)
                    <div class="-mr-2 flex shrink-0">
                        @if ($searchUrl)
                            <button type="button" x-data x-on:click="$dispatch('open-modal', 'cari-global')" class="flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Cari">
                                <x-heroicon-o-magnifying-glass class="size-5" />
                            </button>
                        @endif
                        {{-- Lonceng dan akun di header HP hanya bila belum ada di navigasi bawah (OPD: keduanya di navigasi bawah). --}}
                        @if ($notificationsUrl && ! $bottomNav->contains('route', 'notifications.index'))
                            <x-notification-bell :href="$notificationsUrl" :count="$unreadNotifications" :active="request()->routeIs('notifications.*')" data-bell="mobile" />
                        @endif
                        @if ($accountUrl && ! $bottomNav->contains('route', 'account.password.edit'))
                            <a href="{{ $accountUrl }}" class="flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Akun">
                                <x-heroicon-o-user-circle class="size-5" />
                            </a>
                        @endif
                    </div>
                @endunless
            </div>
        </header>

        {{-- Konten di dalam panel putih (margin 12px, sudut 20px, padding 28px; HP 16px tanpa margin). --}}
        <main id="konten" tabindex="-1" @class([
            'min-h-[calc(100dvh-1.5rem)] bg-panel px-4 pt-5 md:m-3 md:rounded-frame md:border md:border-line md:p-7',
            'pb-24' => ! $focus,
            'pb-8' => $focus,
        ])>
            <div class="mx-auto w-full max-w-app">
                @if (! $header)
                    @unless ($ownHeading)
                        <h1 class="sr-only">{{ $title }}</h1>
                    @endunless
                    @if ($tools !== '')
                        <div class="mb-5 hidden items-center justify-end gap-2 md:flex">{!! $tools !!}</div>
                    @endif
                    {{ $lead ?? '' }}
                @else
                    {{-- Semua peran: kepala halaman seragam di dalam panel; aksi sekunder masuk menu "…" di HP. --}}
                    {{-- condensed: di HP judul sudah ada di header atas; bila header atas memakai judul lain (phoneTitle), h1 tetap tampil. --}}
                    <x-admin.page-header :title="$title" :description="$description" :eyebrow="$eyebrow" :breadcrumbs="$breadcrumbs" :condensed="! $phoneTitle">
                        @if ($tools !== '')
                            <x-slot:tools>{!! $tools !!}</x-slot:tools>
                        @endif
                        @isset($actions)
                            <x-slot:actions>{{ $actions }}</x-slot:actions>
                        @endisset
                        @isset($secondary)
                            <x-slot:secondary>{{ $secondary }}</x-slot:secondary>
                        @endisset
                        @isset($badge)
                            <x-slot:badge>{{ $badge }}</x-slot:badge>
                        @endisset
                        @isset($meta)
                            <x-slot:meta>{{ $meta }}</x-slot:meta>
                        @endisset
                    </x-admin.page-header>
                @endif

                @if ($sectionTabs)
                    {{-- data-swap: URL tab ikut filter yang baru diterapkan x-admin.toolbar (mis. Ekonomi ⇄ Legalitas). --}}
                    <x-admin.tabs
                        data-swap="section-tabs"
                        :class="$header || isset($lead) ? 'mt-5' : ''"
                        :tabs="collect($sectionTabs['tabs'])->map(fn ($item, $key) => ['label' => $item['label'], 'href' => $tabUrls[$key] ?? $item['href'], 'count' => $item['count']])->all()"
                        :active="$tab"
                        :label="$sectionTabs['label']"
                    />
                @endif

                <div class="mt-6">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @if ($searchUrl)
            @include('partials.search-dialog')
        @endif

        {{-- Pesan sistem (sukses, peringatan, gagal) sebagai toast seragam. --}}
        <x-admin.toasts />
    </div>

    {{-- Navigasi bawah HP: maksimal 5 menu, sisanya di "Lainnya" --}}
    @unless ($focus)
    <nav aria-label="Menu utama" x-data="{ more: false }" class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper pb-[env(safe-area-inset-bottom)] md:hidden">
        <ul class="grid h-16 {{ $bottomColumns }}">
            @foreach ($bottomNav as $item)
                @php $active = $item['active'] ?? false; @endphp
                <li>
                    @php
                        $isBell = ($item['route'] ?? null) === 'notifications.index';
                        $navCount = $isBell ? $unreadNotifications : ($item['count'] ?? 0);
                    @endphp
                    <a
                        href="{{ $item['href'] }}"
                        @if ($active) aria-current="page" @endif
                        @if ($navCount > 0) aria-label="{{ $item['short'] ?? $item['label'] }}, {{ $navCount }} {{ $isBell ? 'belum dibaca' : 'menunggu verifikasi' }}" @endif
                        @class([
                            'flex h-full flex-col items-center justify-center gap-0.5 px-1 text-caption',
                            'font-semibold text-brand' => $active,
                            'text-ink-soft' => ! $active,
                        ])
                    >
                        <span class="relative">
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5" />
                            @if ($isBell)
                                @if ($navCount > 0)<x-notification-count :count="$navCount" class="absolute -top-2 left-3" />@endif
                            @else
                                <x-nav-badge :count="$navCount" class="absolute -top-2 left-3" />
                            @endif
                        </span>
                        <span class="w-full truncate text-center">{{ $item['short'] ?? $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
            @if ($moreNav->isNotEmpty())
                <li>
                    <button
                        type="button"
                        x-on:click="more = true"
                        x-bind:aria-expanded="more"
                        @class([
                            'flex h-full w-full flex-col items-center justify-center gap-0.5 px-1 text-caption',
                            'font-semibold text-brand' => $moreNav->contains('active', true),
                            'text-ink-soft' => ! $moreNav->contains('active', true),
                        ])
                    >
                        <x-heroicon-o-ellipsis-horizontal class="size-5" />
                        <span>Lainnya</span>
                    </button>
                </li>
            @endif
        </ul>

        @if ($moreNav->isNotEmpty())
            <div x-cloak x-show="more" x-on:keydown.escape.window="more = false" class="fixed inset-0 z-40">
                <div class="absolute inset-0 bg-ink/40" x-on:click="more = false"></div>
                <div class="absolute inset-x-0 bottom-0 rounded-t-card border-t border-line bg-paper pb-[env(safe-area-inset-bottom)] shadow-float">
                    <div class="flex h-14 items-center justify-between border-b border-line px-4">
                        <p class="text-ui font-semibold">Menu lainnya</p>
                        <button type="button" x-on:click="more = false" class="-mr-2 flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle" aria-label="Tutup">
                            <x-heroicon-o-x-mark class="size-5" />
                        </button>
                    </div>
                    <ul class="py-2">
                        @foreach ($moreNav as $item)
                            @php $active = $item['active'] ?? false; @endphp
                            <li>
                                <a
                                    href="{{ $item['href'] }}"
                                    @if ($active) aria-current="page" @endif
                                    @class([
                                        'flex h-12 items-center gap-3 px-4 text-ui',
                                        'font-semibold text-brand' => $active,
                                        'text-ink hover:bg-subtle' => ! $active,
                                    ])
                                >
                                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5 text-ink-muted" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </nav>
    @endunless
</body>
</html>
