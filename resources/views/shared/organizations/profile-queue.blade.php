{{--
    Tab "Verifikasi profil" (Admin/OPD): Organization::profileReviewQueue = "Perubahan menunggu verifikasi" (diubah kelompok
    setelah diverifikasi, ke tab Riwayat) lalu "Profil belum pernah diverifikasi" (ke tab Profil). Menu titik tiga berisi
    "Tandai terverifikasi" bagi yang berhak (Policy verifyProfile). Variabel: $area, $organizations (paginator).
--}}
@use('App\Support\Format')
@use('App\Support\ProfileReview')
@use('App\Enums\ProfileStatus')

<x-layouts.app
    title="Verifikasi profil"
    section="kelompok"
    tab="profil"
    :description="($area === 'opd' ? 'Profil kelompok binaan' : 'Profil kelompok').' yang belum terverifikasi: perubahan setelah diverifikasi dan profil yang belum pernah diperiksa.'"
>
    <x-admin.card flush>
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 pb-4 pt-4 md:px-5 md:pt-5">
            <h2 class="text-card-head font-semibold text-ink">Antrean verifikasi profil</h2>
            @if ($organizations->isNotEmpty())
                <p class="text-caption text-ink-2 tabular-nums" role="status">{{ Format::number($organizations->total()) }} profil menunggu verifikasi</p>
            @endif
        </div>

        @if ($organizations->isEmpty())
            <x-admin.empty bare icon="identification" title="Semua profil sudah terverifikasi" description="Profil yang baru didaftarkan atau diubah kelompok setelah diverifikasi akan muncul di sini." />
        @else
            <x-admin.table bare clickable>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    <x-admin.table.heading fit>Keadaan</x-admin.table.heading>
                    <x-admin.table.heading>Yang perlu diperiksa</x-admin.table.heading>
                    <x-admin.table.heading fit>Diubah</x-admin.table.heading>
                </x-slot:head>

                @foreach ($organizations as $organization)
                    @php $changed = $organization->profile_status === ProfileStatus::NeedsReview; @endphp
                    <x-admin.table.row :href="$changed ? route($area.'.organizations.history', $organization) : route($area.'.organizations.show', $organization)" :for="$organization->name">
                        <x-admin.table.cell primary><x-admin.identity :name="$organization->name" :subtitle="$organization->institutionType->name.' · '.Format::village($organization->village->name)" /></x-admin.table.cell>
                        <x-admin.table.cell badge><x-admin.badge-status :status="$organization->profile_status" /></x-admin.table.cell>
                        <x-admin.table.cell label="Yang perlu diperiksa">{{ $changed ? implode(', ', ProfileReview::changedLabels($organization)) : 'Seluruh profil' }}</x-admin.table.cell>
                        <x-admin.table.cell label="Diubah" fit>
                            @if ($changed)
                                <x-admin.date :value="$organization->profile_changed_at" time />
                            @else
                                <span class="text-label text-ink-3">Belum pernah</span>
                            @endif
                        </x-admin.table.cell>
                        @can('verifyProfile', $organization)
                            <x-slot:menu>
                                <form method="POST" action="{{ route($area.'.organizations.verify-profile', $organization) }}" data-no-processing>
                                    @csrf
                                    <x-dropdown.item type="submit" icon="check-badge">Tandai terverifikasi</x-dropdown.item>
                                </form>
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
</x-layouts.app>
