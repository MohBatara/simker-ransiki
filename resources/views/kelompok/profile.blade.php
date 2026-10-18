{{-- Profil kelompok sendiri: kepala yang sama dengan detail kelompok Admin/OPD, tanpa tab (menunya ada di sidebar). --}}
<x-layouts.app
    :title="$organization->name"
    :description="$organization->village->name.', Distrik Ransiki'"
    phone-title="Profil Kelompok"
    :header="false"
    own-heading
>
    <x-slot:lead>
        <x-admin.organization-header
            :organization="$organization"
            :links="['produk' => route('kelompok.products.index'), 'izin' => route('kelompok.licenses.index'), 'laporan' => route('kelompok.reports.index')]"
        >
            @can('update', $organization)
                <x-slot:actions>
                    <x-button icon="pencil-square" :href="route('kelompok.profile.edit')">Ubah profil</x-button>
                </x-slot:actions>
            @endcan
        </x-admin.organization-header>
    </x-slot:lead>

    @include('shared.organizations._overview', [
        'area' => 'kelompok',
        'documents' => [
            'title' => 'Dokumen kelompok',
            'description' => 'Mis. foto kegiatan, struktur pengurus, atau akta. JPG, PNG, atau PDF.',
            'uploadUrl' => auth()->user()->can('update', $organization) ? route('attachments.store', ['kelompok', $organization]) : null,
        ],
    ])
</x-layouts.app>
