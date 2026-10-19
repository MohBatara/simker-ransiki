@props([
    'organization',
    // Strip 4 angka ringkas di bawah kepala (butuh *_count dari loadCount).
    'stats' => true,
    // Tautan angka (null = tanpa tautan).
    'links' => [],
    // Tujuan "lengkapi di Ubah profil" pada kartu Anggota yang kosong.
    'editUrl' => null,
])

@use('App\Support\Format')
@use('App\Enums\ProfileStatus')

@php
    $user = auth()->user();
    $sectors = $organization->sectors->pluck('name')->join(', ');
    $canVerify = $user && ! $user->isKelompok() && $organization->profile_status !== ProfileStatus::Verified && $user->can('verifyProfile', $organization);
    $licenses = (int) $organization->licenses_count;
@endphp

{{--
    Kepala detail kelompok (Admin/OPD) dan Profil Kelompok: avatar 56px, nama 24px (maks 2 baris), subjudul "Jenis · Kampung"
    + bidang usaha berikon (teks, bukan chip), lalu pil status kelompok dan status verifikasi profil (+ "Tandai
    terverifikasi" bagi Admin/OPD yang berhak). Aksi di kanan sejajar nama (slot "actions"; slot "menu" = isi menu "…").
    Di bawahnya strip 4 x-admin.stat ringkas: anggota, produk, izin terbit, laporan terverifikasi.
--}}
<div {{ $attributes->class('space-y-6') }}>
    <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-1 items-start gap-4">
            <x-avatar :name="$organization->name" size="xl" class="size-14! text-page-title! max-sm:hidden" />
            <div class="min-w-0">
                <h1 class="line-clamp-2 text-[1.5rem] leading-8 font-semibold text-ink" title="{{ $organization->name }}">{{ $organization->name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-ink-2">
                    <span>{{ $organization->institutionType->name }} <span aria-hidden="true">·</span> {{ $organization->village->name }}</span>
                    @if ($sectors !== '')
                        <span class="inline-flex items-center gap-1.5">
                            <x-heroicon-o-squares-2x2 class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                            <span class="sr-only">Bidang usaha:</span>{{ $sectors }}
                        </span>
                    @endif
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Status kelompok">
                    <x-admin.badge-status :status="$organization->status" />
                    <x-admin.badge-status :status="$organization->profile_status" />
                    @if ($canVerify)
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'verify-profile')" class="inline-flex h-11 items-center gap-1 rounded-pill px-1.5 text-label font-medium text-brand underline underline-offset-4 hover:text-brand-hover md:h-6">
                            <x-heroicon-o-check-badge class="size-4" aria-hidden="true" />
                            Tandai terverifikasi
                        </button>
                    @endif
                </div>
            </div>
        </div>

        @if (isset($actions) || isset($menu))
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{ $actions ?? '' }}
                @isset($menu)
                    <x-dropdown width="w-60">
                        <x-slot:trigger>
                            <x-button variant="secondary" x-bind:aria-expanded="open" aria-haspopup="menu" class="px-2.5" aria-label="Tindakan lain">
                                <x-heroicon-o-ellipsis-vertical class="size-5" />
                            </x-button>
                        </x-slot:trigger>
                        {{ $menu }}
                    </x-dropdown>
                @endisset
            </div>
        @endif
    </header>

    @if ($canVerify)
        <x-admin.confirm name="verify-profile" title="Tandai profil terverifikasi" :description="$organization->name" :action="route($user->role->area().'.organizations.verify-profile', $organization)" confirm="Tandai terverifikasi" tone="brand" processing="Menyimpan…">
            <p>Pastikan data penting profil (nama, jenis, kampung, ketua, kontak, alamat, deskripsi, bidang usaha) sudah sesuai. Status menjadi <strong class="text-ink">Profil terverifikasi</strong>.</p>
        </x-admin.confirm>
    @endif

    @if ($stats)
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-admin.stat compact icon="user-group" label="Anggota" :value="Format::number($organization->members_count)"
                :hint="$organization->members_count === null ? 'lengkapi di Ubah profil' : 'orang'"
                :hint-href="$organization->members_count === null ? $editUrl : null" />
            <x-admin.stat compact icon="cube" label="Produk" :value="Format::number($organization->products_count)" hint="produk terdaftar" :href="$links['produk'] ?? null" />
            <x-admin.stat compact icon="document-check" label="Izin terbit" :value="Format::number($organization->issued_licenses_count)"
                :hint="$licenses === 0 ? 'belum ada izin tercatat' : 'dari '.Format::number($licenses).' izin tercatat'" :href="$links['izin'] ?? null" />
            <x-admin.stat compact icon="clipboard-document-check" label="Laporan terverifikasi" :value="Format::number($organization->verified_reports_count)" :hint="'dari '.Format::number($organization->monthly_reports_count).' laporan'" :href="$links['laporan'] ?? null" />
        </div>
    @endif
</div>
