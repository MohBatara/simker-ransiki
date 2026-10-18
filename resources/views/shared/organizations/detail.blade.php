{{-- Detail kelompok untuk Admin dan OPD. Tab Akun Pengguna, ubah status, dan hapus hanya untuk Admin. --}}
@php
    $tabs = [
        'profil' => 'Profil',
        'produk' => 'Produk',
        'izin' => 'Izin',
        'laporan' => 'Laporan',
        'akun' => 'Akun',
        'riwayat' => 'Riwayat',
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
    $canManage = $area === 'admin' && auth()->user()->can('administer', $organization);
    $canEnterReport = $tab === 'laporan' && ! empty($entryPeriods);
@endphp

<x-layouts.app
    :title="$organization->name"
    :description="$organization->village->name.', Distrik Ransiki'"
    :back="route($area.'.organizations.index')"
    phone-title="Detail kelompok"
    :header="false"
    own-heading
>
    <x-slot:lead>
        <x-admin.organization-header
            :organization="$organization"
            :breadcrumbs="[
                ['label' => $area === 'opd' ? 'Kelompok binaan' : 'Kelompok', 'href' => route($area.'.organizations.index')],
                ['label' => $organization->name],
            ]"
            :links="['produk' => $links['produk'], 'izin' => $links['izin'], 'laporan' => $links['laporan']]"
        >
            <x-slot:actions>
                {{-- Tambah di tab Produk/Izin: tombol header membuka modal milik daftar di bawahnya. --}}
                @if ($canAddProduct)
                    <x-button icon="plus" x-data x-on:click="$dispatch('product-create')">Tambah produk</x-button>
                @elseif ($canAddLicense)
                    <x-button icon="plus" x-data x-on:click="$dispatch('license-create')">Tambah izin</x-button>
                @elseif ($canEnterReport)
                    <x-button icon="pencil-square" x-data x-on:click="$dispatch('open-modal', 'report-entry')">Isi laporan</x-button>
                @endif
                @can('update', $organization)
                    <x-button :variant="$canAddProduct || $canAddLicense || $canEnterReport ? 'secondary' : 'primary'" icon="pencil-square" :href="route($area.'.organizations.edit', $organization)">Ubah profil</x-button>
                @endcan
            </x-slot:actions>
            @if ($canManage || $area === 'admin')
                <x-slot:menu>
                    @can('create', App\Models\User::class)
                        <x-dropdown.item icon="user-plus" :href="route('admin.users.create', ['kelompok' => $organization->id])">Tambah akun</x-dropdown.item>
                    @endcan
                    @if ($canManage)
                        @include('admin.organizations._danger', ['part' => 'menu'])
                        <p class="px-3 py-2 text-caption text-ink-muted">Kelompok dengan laporan atau akun hanya bisa ditutup, tidak dihapus.</p>
                    @endif
                </x-slot:menu>
            @endif
        </x-admin.organization-header>
    </x-slot:lead>

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
