{{-- Profil kelompok sendiri: susunan sama dengan detail kelompok Admin/OPD, tanpa tab (menunya ada di sidebar). --}}
<x-layouts.app
    :title="$organization->name"
    eyebrow="Profil Kelompok"
    :description="$organization->village->name.', Distrik Ransiki'"
    :banner="$banner"
>
    @can('update', $organization)
        <x-slot:actions>
            <x-button variant="secondary" icon="pencil-square" :href="route('kelompok.profile.edit')">Ubah profil</x-button>
        </x-slot:actions>
    @endcan

    @if ($organization->status !== App\Enums\OrganizationStatus::Active)
        <x-slot:badge>
            <x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge>
        </x-slot:badge>
    @endif

    <x-slot:meta>
        <ul class="flex flex-wrap gap-2" aria-label="Jenis dan bidang usaha">
            <li><x-tag>{{ $organization->institutionType->name }}</x-tag></li>
            @foreach ($organization->sectors as $sector)
                <li><x-tag>{{ $sector->name }}</x-tag></li>
            @endforeach
        </ul>
    </x-slot:meta>

    @include('shared.organizations._overview', [
        'area' => 'kelompok',
        'documents' => [
            'title' => 'Dokumen kelompok',
            'description' => 'Mis. foto kegiatan, struktur pengurus, atau akta. JPG, PNG, atau PDF.',
            'uploadUrl' => auth()->user()->can('update', $organization) ? route('attachments.store', ['kelompok', $organization]) : null,
        ],
    ])
</x-layouts.app>
