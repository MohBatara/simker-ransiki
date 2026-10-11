@props([
    'title',
    'description' => null,
    // Keterangan kecil di atas judul, mis. "Dasbor" bila judulnya nama kelompok.
    'eyebrow' => null,
    'back' => null,
    // Remah halaman bertingkat (desktop). Di HP tetap tombol kembali ke $back.
    'breadcrumbs' => null,
    // Banner foto (App\Support\BannerImage) untuk dasbor dan detail kelompok; halaman lain pita forest polos.
    'banner' => null,
    // Banner foto lebih pendek di HP (detail kelompok), agar kartu pertama terlihat tanpa menggulir.
    'compactBanner' => false,
    // Judul header atas HP bila berbeda dari judul halaman (mis. "Detail kelompok"; nama sudah di banner).
    'phoneTitle' => null,
    // Item: ['label', 'href', 'icon' (Heroicons outline), 'short' (label ringkas), 'active', 'primary' (tampil di navigasi bawah HP)]
    // Kosongkan agar diambil otomatis dari peran pengguna yang login (App\Support\Navigation).
    'navigation' => null,
    'notificationsUrl' => null,
    'userName' => null,
    'userRole' => null,
    // Mode form di HP: navigasi bawah disembunyikan, header berisi tombol kembali + judul.
    'focus' => false,
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

    $navigation = collect($navigation);
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
    @include('partials.head', ['title' => $title, 'noindex' => true])
</head>
{{-- --app-bottom: tinggi area tetap di bawah layar HP, dipakai x-form-actions sticky. --}}
<body @class([
    'min-h-dvh bg-canvas font-sans text-ui text-ink antialiased',
    '[--app-bottom:calc(4rem+env(safe-area-inset-bottom))] [--app-safe-bottom:0px]' => ! $focus,
    '[--app-bottom:0px] [--app-safe-bottom:env(safe-area-inset-bottom)]' => $focus,
])>
    @include('partials.skip-link')
    {{-- Sidebar: ikon + label ringkas di tablet (72px), penuh di desktop (248px) --}}
    <aside class="hidden md:fixed md:inset-y-0 md:left-0 md:z-20 md:flex md:w-[72px] md:flex-col md:border-r md:border-line md:bg-paper xl:w-[248px]">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-line px-2 max-xl:justify-center xl:px-5" title="SIM-KER Distrik Ransiki">
            <x-logo />
            <div class="hidden min-w-0 leading-tight xl:block">
                <span class="block text-ui font-semibold text-ink">SIM-KER</span>
                <span class="block text-caption text-ink-muted">Distrik Ransiki</span>
            </div>
            <span class="sr-only xl:hidden">SIM-KER Distrik Ransiki</span>
        </div>

        {{-- Di tablet nav tidak di-scroll agar tooltip tidak terpotong oleh tepi sidebar. --}}
        <nav aria-label="Menu utama" class="flex-1 px-1 py-3 xl:overflow-y-auto xl:px-3">
            <ul class="space-y-1">
                {{-- Notifikasi tidak di sidebar: lonceng ada di bilah atas (tablet dan desktop). --}}
                @foreach ($navigation->reject(fn ($item) => ($item['route'] ?? null) === 'notifications.index') as $item)
                    @php $active = $item['active'] ?? false; @endphp
                    <li>
                        <a
                            href="{{ $item['href'] }}"
                            aria-label="{{ $item['label'] }}{{ ($item['route'] ?? null) === 'notifications.index' && $unreadNotifications > 0 ? ', '.$unreadNotifications.' belum dibaca' : '' }}"
                            @if ($active) aria-current="page" @endif
                            @class([
                                'group relative flex flex-col items-center gap-1 rounded-control px-1 py-2 transition-colors xl:flex-row xl:gap-3 xl:px-3',
                                'bg-brand-tint font-semibold text-brand' => $active,
                                'text-ink-soft hover:bg-subtle hover:text-ink' => ! $active,
                            ])
                        >
                            @php $navCount = ($item['route'] ?? null) === 'notifications.index' ? $unreadNotifications : 0; @endphp
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5 shrink-0" />
                            <span class="w-full truncate text-center text-micro xl:hidden" aria-hidden="true">{{ $item['short'] ?? $item['label'] }}</span>
                            <span class="hidden truncate text-ui xl:inline" aria-hidden="true">{{ $item['label'] }}</span>
                            @if ($navCount > 0)
                                <x-notification-count :count="$navCount" class="absolute right-2 top-1 xl:static xl:ml-auto" />
                            @endif
                            <span aria-hidden="true" class="pointer-events-none absolute left-full top-1/2 z-30 ml-2 -translate-y-1/2 whitespace-nowrap rounded-control bg-ink px-2 py-1 text-caption font-normal text-paper opacity-0 shadow-float transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 xl:hidden">
                                {{ $item['label'] }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

    </aside>

    <div class="md:pl-[72px] xl:pl-[248px]">
        {{-- Bilah atas tablet/desktop: cari global (Admin, OPD), lonceng, menu profil. --}}
        <header class="sticky top-0 z-20 hidden h-16 items-center gap-3 border-b border-line bg-paper px-6 md:flex xl:px-8">
            @if ($searchUrl)
                <form method="GET" action="{{ $searchUrl }}" role="search" class="min-w-0 max-w-lg flex-1">
                    <x-search-input label="Cari kelompok, ketua, kampung, atau produk" :value="request()->routeIs('*.search') ? request('q') : null" />
                </form>
            @else
                <div class="flex-1"></div>
            @endif
            <div class="ml-auto flex items-center gap-2">
                @if ($notificationsUrl)
                    <x-notification-bell :href="$notificationsUrl" :count="$unreadNotifications" :active="request()->routeIs('notifications.*')" data-bell="desktop" />
                @endif
                @if ($userName)
                    <x-dropdown width="w-64">
                        <x-slot:trigger>
                            <button type="button" x-bind:aria-expanded="open" aria-haspopup="menu" class="flex min-h-11 items-center gap-3 rounded-control px-2 text-left transition-colors hover:bg-subtle md:min-h-10">
                                <x-avatar :name="$userName" size="sm" />
                                <span class="hidden min-w-0 max-w-48 leading-tight lg:block">
                                    <span class="block truncate text-ui font-semibold text-ink">{{ $userName }}</span>
                                    @if ($userRole)
                                        <span class="block truncate text-caption text-ink-muted">{{ $userRole }}</span>
                                    @endif
                                </span>
                                <x-heroicon-o-chevron-down class="size-4 text-ink-muted" />
                                <span class="sr-only">Menu akun {{ $userName }}</span>
                            </button>
                        </x-slot:trigger>
                        <div class="border-b border-line px-3 pb-2 pt-1">
                            <p class="line-clamp-2 text-ui font-semibold text-ink">{{ $userName }}</p>
                            <p class="truncate text-caption text-ink-muted">{{ $userRole }}</p>
                        </div>
                        @if ($accountUrl)
                            <x-dropdown.item :href="$accountUrl" icon="user-circle">Akun Saya</x-dropdown.item>
                        @endif
                        @if (Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}" data-logout>
                                @csrf
                                <x-dropdown.item type="submit" icon="arrow-right-start-on-rectangle">Keluar</x-dropdown.item>
                            </form>
                        @endif
                    </x-dropdown>
                @endif
            </div>
        </header>

        {{-- Header atas tipis di HP --}}
        <header class="sticky top-0 z-20 border-b border-line bg-paper pt-[env(safe-area-inset-top)] md:hidden">
            <div class="flex h-14 items-center gap-1 px-4">
                @if ($back)
                    <a href="{{ $back }}" class="-ml-2 flex size-11 shrink-0 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Kembali">
                        <x-heroicon-o-arrow-left class="size-5" />
                    </a>
                @endif
                <p class="min-w-0 flex-1 truncate text-ui font-semibold text-ink">{{ $phoneTitle ?? $title }}</p>
                @unless ($focus)
                    <div class="-mr-2 flex shrink-0">
                        @if ($searchUrl)
                            <a href="{{ $searchUrl }}" class="flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Cari">
                                <x-heroicon-o-magnifying-glass class="size-5" />
                            </a>
                        @endif
                        @if ($notificationsUrl)
                            <x-notification-bell :href="$notificationsUrl" :count="$unreadNotifications" :active="request()->routeIs('notifications.*')" data-bell="mobile" />
                        @endif
                        {{-- Jalan ke halaman akun dan tombol keluar di HP, karena menu admin tidak memuat Akun Saya. --}}
                        @if ($accountUrl)
                            <a href="{{ $accountUrl }}" class="flex size-11 items-center justify-center rounded-control text-ink-soft hover:bg-subtle hover:text-ink" aria-label="Akun">
                                <x-heroicon-o-user-circle class="size-5" />
                            </a>
                        @endif
                    </div>
                @endunless
            </div>
        </header>

        <main id="konten" tabindex="-1" @class([
            'mx-auto w-full max-w-app px-4 pt-5 md:px-6 md:pb-10 md:pt-8 xl:px-8',
            'pb-24' => ! $focus,
            'pb-8' => $focus,
        ])>
            <div class="flex items-start gap-3">
                <x-page-header class="min-w-0 flex-1" :title="$title" :description="$description" :eyebrow="$eyebrow" :back="$back" :breadcrumbs="$breadcrumbs" :band="! $focus" :image="$banner" :compact="$compactBanner" condensed>
                    @isset($actions)
                        <x-slot:actions>{{ $actions }}</x-slot:actions>
                    @endisset
                    @isset($badge)
                        <x-slot:badge>{{ $badge }}</x-slot:badge>
                    @endisset
                    @isset($meta)
                        <x-slot:meta>{{ $meta }}</x-slot:meta>
                    @endisset
                </x-page-header>
            </div>

            @if (session('status'))
                <x-alert tone="ok" class="mt-5" dismissible>{{ session('status') }}</x-alert>
            @endif
            @if (session('error'))
                <x-alert tone="fix" class="mt-5" dismissible>{{ session('error') }}</x-alert>
            @endif

            <div class="mt-5 md:mt-6">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Navigasi bawah HP: maksimal 5 menu, sisanya di "Lainnya" --}}
    @unless ($focus)
    <nav aria-label="Menu utama" x-data="{ more: false }" class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper pb-[env(safe-area-inset-bottom)] md:hidden">
        <ul class="grid h-16 {{ $bottomColumns }}">
            @foreach ($bottomNav as $item)
                @php $active = $item['active'] ?? false; @endphp
                <li>
                    <a
                        href="{{ $item['href'] }}"
                        @if ($active) aria-current="page" @endif
                        @class([
                            'flex h-full flex-col items-center justify-center gap-0.5 px-1 text-caption',
                            'font-semibold text-brand' => $active,
                            'text-ink-soft' => ! $active,
                        ])
                    >
                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5" />
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
