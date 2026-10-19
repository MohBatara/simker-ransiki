{{--
    Tab "Verifikasi profil" (Admin/OPD): chip "Perubahan menunggu (N)" dan "Belum pernah diverifikasi (N)" memilah
    Organization::profileReviewQueue (keadaan ditandai chip, bukan pil di tiap baris). Perubahan: Kelompok, Yang perlu
    diperiksa, Diubah (ke tab Riwayat). Belum pernah: Kelompok, Kampung, Ditambahkan (ke tab Profil). Menu titik tiga berisi
    "Tandai terverifikasi" bagi yang berhak (Policy verifyProfile). Variabel: $area, $organizations, $kind, $kindCounts.
--}}
@use('App\Support\Format')
@use('App\Support\ProfileReview')

@php
    $changes = $kind === 'perubahan';
    $chips = ['perubahan' => 'Perubahan menunggu', 'baru' => 'Belum pernah diverifikasi'];
@endphp

<x-layouts.app
    title="Verifikasi profil"
    section="kelompok"
    tab="profil"
    :description="($area === 'opd' ? 'Profil kelompok binaan' : 'Profil kelompok').' yang belum terverifikasi: perubahan dari akun kelompok dan profil yang belum pernah diperiksa.'"
>
    <div x-data="{ verifying: { url: '', name: '' } }">
    {{-- Klik baris membuka popup pratinjau kelompok tanpa pindah halaman. --}}
    <div x-data="organizationPanel(@js(route($area.'.organizations.summary', ['organization' => '__ID__'])))" x-on:click="select($event)">
    <x-admin.card flush>
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 pb-4 pt-4 md:px-5 md:pt-5">
            <h2 class="text-card-head font-semibold text-ink">Antrean verifikasi profil</h2>
            <nav aria-label="Jenis antrean" class="flex flex-wrap gap-2">
                @foreach ($chips as $key => $label)
                    <a href="{{ route($area.'.profile-verifications.index', ['jenis' => $key]) }}" @if ($kind === $key) aria-current="page" @endif @class([
                        'inline-flex h-11 items-center gap-1.5 rounded-pill border px-3 text-caption font-medium transition-colors md:h-8',
                        'border-brand-100 bg-brand-50 text-brand' => $kind === $key,
                        'border-line text-ink-2 hover:bg-hover hover:text-ink' => $kind !== $key,
                    ])>{{ $label }} <span class="tabular-nums">({{ Format::number($kindCounts[$key]) }})</span></a>
                @endforeach
            </nav>
        </div>

        @if ($organizations->isEmpty())
            <x-admin.empty bare icon="identification"
                :title="$changes ? 'Tidak ada perubahan yang menunggu' : 'Semua profil sudah pernah diverifikasi'"
                :description="$changes ? 'Perubahan data penting oleh akun kelompok setelah profilnya diverifikasi akan muncul di sini.' : 'Kelompok yang dibuat atau diimpor petugas langsung terverifikasi.'" />
        @else
            <x-admin.table bare clickable>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    @if ($changes)
                        <x-admin.table.heading>Yang perlu diperiksa</x-admin.table.heading>
                        <x-admin.table.heading fit>Diubah</x-admin.table.heading>
                    @else
                        <x-admin.table.heading fit>Kampung</x-admin.table.heading>
                        <x-admin.table.heading fit>Ditambahkan</x-admin.table.heading>
                    @endif
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <x-admin.table.row :href="route($area.'.organizations.show', $organization)" :for="$organization->name" view-label="Buka detail" :data-preview-id="$organization->id" class="data-[selected]:bg-brand-50">
                        <x-admin.table.cell primary><x-admin.identity :name="$organization->name" :subtitle="$organization->institutionType->name" /></x-admin.table.cell>
                        @if ($changes)
                            <x-admin.table.cell label="Yang perlu diperiksa">{{ implode(', ', ProfileReview::changedLabels($organization)) }}</x-admin.table.cell>
                            <x-admin.table.cell label="Diubah" fit><x-admin.date :value="$organization->profile_changed_at" time /></x-admin.table.cell>
                        @else
                            <x-admin.table.cell label="Kampung" fit>{{ Format::village($organization->village->name) }}</x-admin.table.cell>
                            <x-admin.table.cell label="Ditambahkan" fit><x-admin.date :value="$organization->created_at" /></x-admin.table.cell>
                        @endif
                        @can('verifyProfile', $organization)
                            <x-slot:menu>
                                @if ($changes)
                                    <x-dropdown.item icon="clock" :href="route($area.'.organizations.history', $organization)">Lihat riwayat perubahan</x-dropdown.item>
                                @endif
                                <x-dropdown.item icon="check-badge" x-on:click="verifying = {{ Js::from(['url' => route($area.'.organizations.verify-profile', $organization), 'name' => $organization->name]) }}; $dispatch('open-modal', 'verify-profile')">Tandai terverifikasi</x-dropdown.item>
                            </x-slot:menu>
                        @endcan
                    </x-admin.table.row>
                @endforeach

                <x-slot:footer>
                    <x-admin.pagination :paginator="$organizations" noun="profil" />
                </x-slot:footer>
            </x-admin.table>
        @endif
    </x-admin.card>

    @include('shared.organizations._preview-modal', ['panel' => null])
    </div>

    {{-- Menu titik tiga "Tandai terverifikasi" membuka konfirmasi ini (form mengikuti baris yang dipilih). --}}
    <x-admin.confirm name="verify-profile" title="Tandai profil terverifikasi" bind-action="verifying.url" confirm="Tandai terverifikasi" tone="brand" processing="Menyimpan…">
        <p>Tandai profil <strong class="text-ink" x-text="verifying.name"></strong> terverifikasi? Pastikan data pentingnya sudah sesuai.</p>
    </x-admin.confirm>
    </div>
</x-layouts.app>
