{{-- Detail kelompok untuk Admin dan OPD. Tab Akun Pengguna, ubah status, dan hapus hanya untuk Admin. --}}
@php
    $tabs = [
        'profil' => 'Profil',
        'produk' => 'Produk',
        'izin' => 'Izin',
        'laporan' => 'Laporan',
        'riwayat' => 'Riwayat',
        'akun' => 'Akun',
    ];
    $counts = ['produk' => $organization->products_count, 'izin' => $organization->licenses_count, 'laporan' => $organization->monthly_reports_count, 'akun' => $organization->users_count];
    if ($area !== 'admin') {
        unset($tabs['akun']);
    }
    $links = [
        'profil' => route($area.'.organizations.show', $organization),
        'produk' => route($area.'.organizations.products', $organization),
        'izin' => route($area.'.organizations.licenses', $organization),
        'laporan' => route($area.'.organizations.reports', $organization),
        'riwayat' => route($area.'.organizations.history', $organization),
        'akun' => $area === 'admin' ? route('admin.organizations.users', $organization) : null,
    ];
    if (auth()->user()->cannot('viewHistory', $organization)) {
        unset($tabs['riwayat']);
    }
    $icons = ['profil' => 'identification', 'produk' => 'cube', 'izin' => 'document-check', 'laporan' => 'document-text', 'riwayat' => 'clock', 'akun' => 'users'];
    $canAddProduct = $tab === 'produk' && auth()->user()->can('create', [App\Models\Product::class, $organization]);
    $canAddLicense = $tab === 'izin' && auth()->user()->can('create', [App\Models\License::class, $organization]);
    $canAddUser = $tab === 'akun' && auth()->user()->can('create', App\Models\User::class);
    $canManage = $area === 'admin' && auth()->user()->can('administer', $organization);
    $canEnterReport = $tab === 'laporan' && ! empty($entryPeriods);
@endphp

<x-layouts.app
    :title="$organization->name"
    :description="$organization->village->name.', Distrik Ransiki'"
    :back="route($area.'.organizations.index')"
    :breadcrumbs="[
        ['label' => $area === 'opd' ? 'Kelompok binaan' : 'Kelompok', 'href' => route($area.'.organizations.index')],
        ['label' => $organization->name],
    ]"
    phone-title="Detail kelompok"
>
    @can('update', $organization)
        <x-slot:secondary>
            <x-button variant="secondary" icon="pencil-square" :href="route($area.'.organizations.edit', $organization)">Ubah profil</x-button>
        </x-slot:secondary>
    @endcan
    @if ($canAddProduct || $canAddLicense || $canAddUser || $canEnterReport || $canManage)
        <x-slot:actions>
            {{-- Tambah di tab Produk/Izin: tombol header membuka modal milik daftar di bawahnya. --}}
            @if ($canAddProduct)
                <x-button icon="plus" x-data x-on:click="$dispatch('product-create')">Tambah produk</x-button>
            @elseif ($canAddLicense)
                <x-button icon="plus" x-data x-on:click="$dispatch('license-create')">Tambah izin</x-button>
            @elseif ($canEnterReport)
                <x-button icon="pencil-square" x-data x-on:click="$dispatch('open-modal', 'report-entry')">Isi laporan</x-button>
            @elseif ($canAddUser)
                <x-button icon="plus" :href="route('admin.users.create', ['kelompok' => $organization->id])">Tambah akun</x-button>
            @endif
            @if ($canManage)
                <x-dropdown width="w-56">
                    <x-slot:trigger>
                        <x-button variant="secondary" x-bind:aria-expanded="open" aria-haspopup="menu" class="px-3" aria-label="Tindakan lain">
                            <x-heroicon-o-ellipsis-horizontal class="size-5" />
                        </x-button>
                    </x-slot:trigger>
                    @include('admin.organizations._danger', ['part' => 'menu'])
                    <p class="px-3 py-2 text-caption text-ink-muted">Kelompok dengan laporan atau akun hanya bisa ditutup, tidak dihapus.</p>
                </x-dropdown>
            @endif
        </x-slot:actions>
    @endif

    <x-slot:badge>
        <x-admin.badge-status :status="$organization->status" />
    </x-slot:badge>

    <x-slot:meta>
        <ul class="flex flex-wrap gap-2" aria-label="Jenis dan bidang usaha">
            <li><x-tag>{{ $organization->institutionType->name }}</x-tag></li>
            @foreach ($organization->sectors as $sector)
                <li><x-tag>{{ $sector->name }}</x-tag></li>
            @endforeach
        </ul>
    </x-slot:meta>

    <div class="space-y-6">
        @if ($area === 'admin' && $organization->users_count === 0)
            <x-alert tone="wait">
                Kelompok ini belum punya akun, jadi belum bisa mengisi laporan.
                <x-link :href="route('admin.users.create', ['kelompok' => $organization->id])" inline>Buat akun</x-link>
            </x-alert>
        @endif

        @if ($area === 'admin' && session('duplicates'))
            @include('shared.organizations._duplicates', ['candidates' => session('duplicates'), 'confirm' => false])
        @endif

        <x-admin.tabs :tabs="collect($tabs)->map(fn ($label, $key) => ['label' => $label, 'href' => $links[$key], 'icon' => $icons[$key], 'count' => $counts[$key] ?? null])->all()" :active="$tab" label="Bagian detail kelompok" />

        @switch($tab)
            @case('profil')
                @include('shared.organizations._overview', ['documents' => [
                    'title' => 'Dokumen kelompok',
                    'uploadUrl' => auth()->user()->can('update', $organization) ? route('attachments.store', ['kelompok', $organization]) : null,
                ]])
                @break
            @case('produk')
                @include('shared.organizations.products', [
                    'canManage' => auth()->user()->can('create', [App\Models\Product::class, $organization]),
                    'canFeature' => auth()->user()->isAdmin(),
                    'canPhoto' => auth()->user()->can('update', $organization),
                    'urls' => [
                        'store' => route($area.'.organizations.products.store', $organization),
                        'update' => route($area.'.organizations.products.update', [$organization, '__ID__']),
                        'destroy' => route($area.'.organizations.products.destroy', [$organization, '__ID__']),
                    ],
                ])
                @break
            @case('izin')
                @include('shared.organizations.licenses', [
                    'canManage' => auth()->user()->can('create', [App\Models\License::class, $organization]),
                    'licenseUrl' => route($area.'.organizations.licenses.show', [$organization, '__ID__']),
                    'urls' => [
                        'store' => route($area.'.organizations.licenses.store', $organization),
                        'update' => route($area.'.organizations.licenses.update', [$organization, '__ID__']),
                        'destroy' => route($area.'.organizations.licenses.destroy', [$organization, '__ID__']),
                    ],
                ])
                @break
            @case('laporan')
                @include('shared.organizations.reports')
                @break
            @case('riwayat')
                @include('shared.organizations.history')
                @break
            @case('akun')
                @include('admin.organizations._users')
                @break
        @endswitch
    </div>

    @if ($canManage)
        @include('admin.organizations._danger', ['part' => 'modals'])
    @endif
</x-layouts.app>
