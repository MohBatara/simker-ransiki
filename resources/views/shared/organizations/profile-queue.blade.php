{{-- Profil menunggu verifikasi (Admin/OPD). Variabel: $area, $organizations (paginator). --}}
@use('App\Support\Format')
@use('App\Support\ProfileReview')

<x-layouts.app
    title="Verifikasi Profil"
    :description="$area === 'opd'
        ? 'Kelompok binaan yang mengubah data penting profilnya setelah diverifikasi. Periksa perubahannya di tab Riwayat, lalu tandai terverifikasi.'
        : 'Kelompok yang mengubah data penting profilnya setelah diverifikasi. Periksa perubahannya di tab Riwayat, lalu tandai terverifikasi.'"
>
    <div class="space-y-4">
        @if ($organizations->isEmpty())
            <x-empty-state title="Tidak ada profil yang menunggu verifikasi" description="Profil yang diubah kelompok setelah diverifikasi akan muncul di sini." />
        @else
            <p class="text-ink-soft">{{ $organizations->total() }} profil menunggu verifikasi</p>
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading>Field yang berubah</x-table.heading>
                    <x-table.heading>Diubah</x-table.heading>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    <x-table.row :href="route($area.'.organizations.history', $organization)">
                        <x-table.cell primary>{{ $organization->name }}</x-table.cell>
                        <x-table.cell subtitle class="md:hidden">{{ $organization->institutionType->name }} · {{ $organization->village->name }}</x-table.cell>
                        <x-table.cell label="Field yang berubah">{{ implode(', ', ProfileReview::changedLabels($organization)) }}</x-table.cell>
                        <x-table.cell label="Diubah" class="whitespace-nowrap">{{ Format::relativeDateTime($organization->profile_changed_at) }}</x-table.cell>
                    </x-table.row>
                @endforeach

                @if ($organizations->hasPages())
                    <x-slot:footer>{{ $organizations->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    </div>
</x-layouts.app>
