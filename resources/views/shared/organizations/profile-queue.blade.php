{{-- Profil menunggu verifikasi (Admin/OPD). Variabel: $area, $organizations (paginator). --}}
@use('App\Support\Format')
@use('App\Support\ProfileReview')

<x-layouts.app
    title="Menunggu verifikasi profil"
    section="kelompok"
    tab="profil"
    :description="$area === 'opd'
        ? 'Kelompok binaan yang mengubah data penting profilnya setelah diverifikasi. Periksa perubahannya di tab Riwayat, lalu tandai terverifikasi.'
        : 'Kelompok yang mengubah data penting profilnya setelah diverifikasi. Periksa perubahannya di tab Riwayat, lalu tandai terverifikasi.'"
>
    <div class="space-y-4">
        @if ($organizations->isEmpty())
            <x-admin.empty icon="identification" title="Tidak ada profil yang menunggu verifikasi" description="Profil yang diubah kelompok setelah diverifikasi akan muncul di sini." />
        @else
            <p class="text-ui text-ink-soft tabular-nums" role="status">{{ Format::number($organizations->total()) }} profil menunggu verifikasi</p>
            <x-admin.table clickable>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    <x-admin.table.heading>Field yang berubah</x-admin.table.heading>
                    <x-admin.table.heading>Diubah</x-admin.table.heading>
                    <x-admin.table.heading>Status</x-admin.table.heading>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <x-admin.table.row :href="route($area.'.organizations.history', $organization)">
                        <x-admin.table.cell primary>{{ $organization->name }}</x-admin.table.cell>
                        <x-admin.table.cell subtitle class="md:hidden">{{ $organization->institutionType->name }} · {{ $organization->village->name }}</x-admin.table.cell>
                        <x-admin.table.cell label="Field yang berubah">{{ implode(', ', ProfileReview::changedLabels($organization)) }}</x-admin.table.cell>
                        <x-admin.table.cell label="Diubah"><x-admin.date :value="$organization->profile_changed_at" time /></x-admin.table.cell>
                        <x-admin.table.cell badge><x-admin.badge-status :status="$organization->profile_status" /></x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach

                <x-slot:footer>
                    <x-admin.pagination :paginator="$organizations" noun="profil" />
                </x-slot:footer>
            </x-admin.table>
        @endif
    </div>
</x-layouts.app>
