{{-- Detail kelompok untuk Admin dan OPD. Tab Akun Pengguna dan tutup/hapus hanya untuk Admin. --}}
@php
    $tabs = [
        'profil' => 'Profil',
        'produk' => 'Produk ('.$organization->products_count.')',
        'izin' => 'Izin ('.$organization->licenses_count.')',
        'laporan' => 'Laporan ('.$organization->monthly_reports_count.')',
        'akun' => 'Akun Pengguna ('.$organization->users_count.')',
    ];
    if ($area !== 'admin') {
        unset($tabs['akun']);
    }
    $links = [
        'profil' => route($area.'.organizations.show', $organization),
        'produk' => route($area.'.organizations.products', $organization),
        'izin' => route($area.'.organizations.licenses', $organization),
        'laporan' => route($area.'.organizations.reports', $organization),
        'akun' => $area === 'admin' ? route('admin.organizations.users', $organization) : null,
    ];
@endphp

<x-layouts.app
    :title="$organization->name"
    :description="$organization->institutionType->name.' · '.$organization->village->name"
    :back="route($area.'.organizations.index')"
>
    @can('update', $organization)
        <x-slot:actions>
            <x-button variant="secondary" icon="pencil-square" :href="route($area.'.organizations.edit', $organization)">Ubah profil</x-button>
        </x-slot:actions>
    @endcan

    @if ($organization->status !== App\Enums\OrganizationStatus::Active)
        <x-slot:badge>
            <x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge>
        </x-slot:badge>
    @endif

    <x-slot:meta>
        @include('shared.organizations._facts')
    </x-slot:meta>

    <div class="space-y-5">

        @if (session('similar'))
            <x-alert tone="wait" title="Ada kelompok dengan nama mirip di kampung yang sama">
                <p>Periksa apakah datanya ganda:
                    @foreach (session('similar') as $other)
                        <a href="{{ route($area.'.organizations.show', $other['id']) }}" class="text-brand underline">{{ $other['name'] }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            </x-alert>
        @endif

        <x-tabs :tabs="$tabs" :links="$links" :active="$tab" />

        @switch($tab)
            @case('profil')
                @include('shared.organizations.profile', ['documents' => [
                    'title' => 'Dokumen kelompok',
                    'uploadUrl' => auth()->user()->can('update', $organization) ? route('attachments.store', ['kelompok', $organization]) : null,
                ]])
                @if ($area === 'admin')
                    @include('admin.organizations._danger')
                @endif
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
            @case('akun')
                @include('admin.organizations._users')
                @break
        @endswitch
    </div>
</x-layouts.app>
