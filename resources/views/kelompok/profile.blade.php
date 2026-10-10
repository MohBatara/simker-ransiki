<x-layouts.app title="Profil Kelompok" :description="$organization->name.' · '.$organization->institutionType->name.' · '.$organization->village->name">
    @can('update', $organization)
        <x-slot:actions>
            <x-button variant="secondary" icon="pencil-square" :href="route('kelompok.profile.edit')">Ubah profil</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-5">
        <div class="flex flex-wrap gap-2">
            @if ($organization->status !== App\Enums\OrganizationStatus::Active)<x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge>@endif
        </div>

        @include('shared.organizations.profile')

        @include('shared.attachments', [
            'title' => 'Dokumen kelompok',
            'description' => 'Mis. foto kegiatan, struktur pengurus, atau akta. JPG, PNG, atau PDF.',
            'uploadUrl' => auth()->user()->can('update', $organization) ? route('attachments.store', ['kelompok', $organization]) : null,
        ])
    </div>
</x-layouts.app>
