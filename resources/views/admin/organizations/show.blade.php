@php
    $tabs = [
        'profil' => 'Profil',
        'produk' => 'Produk ('.$organization->products_count.')',
        'izin' => 'Izin ('.$organization->licenses_count.')',
        'laporan' => 'Laporan ('.$organization->monthly_reports_count.')',
        'akun' => 'Akun Pengguna ('.$organization->users_count.')',
    ];
    $links = [
        'profil' => route('admin.organizations.show', $organization),
        'produk' => route('admin.organizations.products', $organization),
        'izin' => route('admin.organizations.licenses', $organization),
        'laporan' => route('admin.organizations.reports', $organization),
        'akun' => route('admin.organizations.users', $organization),
    ];
@endphp

<x-layouts.app
    :title="$organization->name"
    :description="$organization->institutionType->name.' · '.$organization->village->name"
    :back="route('admin.organizations.index')"
>
    @can('update', $organization)
        <x-slot:actions>
            <x-button variant="secondary" icon="pencil-square" :href="route('admin.organizations.edit', $organization)">Ubah profil</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-5">
        <div class="flex flex-wrap gap-2">
            <x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge>
            @if ($organization->opds_count === 0)
                <x-badge tone="wait">Belum ada OPD pembina</x-badge>
            @endif
        </div>

        @if (session('similar'))
            <x-alert tone="wait" title="Ada kelompok dengan nama mirip di kampung yang sama">
                <p>Periksa apakah datanya ganda:
                    @foreach (session('similar') as $other)
                        <a href="{{ route('admin.organizations.show', $other['id']) }}" class="text-brand underline">{{ $other['name'] }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            </x-alert>
        @endif

        <x-tabs :tabs="$tabs" :links="$links" :active="$tab" />

        @switch($tab)
            @case('profil')
                @include('shared.organizations.profile')
                @include('admin.organizations._danger')
                @break
            @case('produk')
                @include('shared.organizations.products', [
                    'canManage' => auth()->user()->can('create', [App\Models\Product::class, $organization]),
                    'canFeature' => auth()->user()->isAdmin(),
                    'urls' => [
                        'store' => route('admin.organizations.products.store', $organization),
                        'update' => route('admin.organizations.products.update', [$organization, '__ID__']),
                        'destroy' => route('admin.organizations.products.destroy', [$organization, '__ID__']),
                    ],
                ])
                @break
            @case('izin')
                @include('shared.organizations.licenses', [
                    'canManage' => auth()->user()->can('create', [App\Models\License::class, $organization]),
                    'urls' => [
                        'store' => route('admin.organizations.licenses.store', $organization),
                        'update' => route('admin.organizations.licenses.update', [$organization, '__ID__']),
                        'destroy' => route('admin.organizations.licenses.destroy', [$organization, '__ID__']),
                    ],
                ])
                @break
            @case('laporan')
                @include('admin.organizations._reports')
                @break
            @case('akun')
                @include('admin.organizations._users')
                @break
        @endswitch
    </div>
</x-layouts.app>
