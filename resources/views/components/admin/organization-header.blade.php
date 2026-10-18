@props([
    'organization',
    'breadcrumbs' => null,
    // Strip 4 angka ringkas di bawah kepala (butuh *_count dari loadCount).
    'stats' => true,
    // Tautan angka (null = tanpa tautan).
    'links' => [],
])

@use('App\Support\Format')

{{--
    Kepala detail kelompok (Admin/OPD) dan Profil Kelompok: inisial, nama, jenis + kampung, badge status, lencana
    verifikasi profil, aksi di kanan (slot "actions"; slot "menu" = isi menu "…"). Di bawahnya strip 4 x-admin.stat ringkas:
    anggota, produk, izin terbit, laporan terverifikasi.
--}}
<div {{ $attributes->class('space-y-6') }}>
    <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-1 gap-4">
            <x-avatar :name="$organization->name" size="xl" class="max-sm:hidden" />
            <div class="min-w-0">
                @if ($breadcrumbs)
                    <x-breadcrumbs :items="$breadcrumbs" class="mb-2" />
                @endif
                <h1 class="text-admin-title font-semibold text-ink">{{ $organization->name }}</h1>
                <p class="mt-1 text-ui text-ink-soft md:text-body">
                    {{ $organization->institutionType->name }} <span aria-hidden="true">·</span> {{ $organization->village->name }}, Distrik Ransiki
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Status kelompok">
                    <x-admin.badge-status :status="$organization->status" />
                    <x-admin.badge-status :status="$organization->profile_status" />
                    @foreach ($organization->sectors as $sector)
                        <span class="inline-flex items-center rounded-full border border-line bg-paper px-2.5 py-0.5 text-caption text-ink-soft">{{ $sector->name }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @if (isset($actions) || isset($menu))
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{ $actions ?? '' }}
                @isset($menu)
                    <x-dropdown width="w-60">
                        <x-slot:trigger>
                            <x-button variant="secondary" x-bind:aria-expanded="open" aria-haspopup="menu" class="px-3" aria-label="Tindakan lain">
                                <x-heroicon-o-ellipsis-horizontal class="size-5" />
                            </x-button>
                        </x-slot:trigger>
                        {{ $menu }}
                    </x-dropdown>
                @endisset
            </div>
        @endif
    </header>

    @if ($stats)
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-admin.stat compact icon="user-group" label="Anggota" :value="Format::number($organization->members_count)" :hint="$organization->members_count === null ? 'belum diisi' : 'orang'" />
            <x-admin.stat compact icon="cube" label="Produk" :value="Format::number($organization->products_count)" :href="$links['produk'] ?? null" />
            <x-admin.stat compact icon="document-check" label="Izin terbit" :value="Format::number($organization->issued_licenses_count)" :hint="'dari '.Format::number($organization->licenses_count).' izin'" :href="$links['izin'] ?? null" />
            <x-admin.stat compact icon="clipboard-document-check" label="Laporan terverifikasi" :value="Format::number($organization->verified_reports_count)" :hint="'dari '.Format::number($organization->monthly_reports_count).' laporan'" :href="$links['laporan'] ?? null" />
        </div>
    @endif
</div>
