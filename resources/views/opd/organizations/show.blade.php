<x-layouts.app
    :title="$organization->name"
    :description="$organization->institutionType->name.' · '.$organization->village->name"
    :back="route('opd.dashboard')"
>
    <x-slot:actions>
        @can('update', $organization)
            <x-button variant="secondary" :href="route('opd.organizations.edit', $organization)" icon="pencil-square">Ubah profil</x-button>
        @endcan
    </x-slot:actions>

    <div class="max-w-2xl">
        <x-card title="Profil singkat">
            <dl class="divide-y divide-line">
                <div class="flex justify-between gap-4 py-2 first:pt-0">
                    <dt class="text-ink-soft">Status</dt>
                    <dd><x-badge :tone="$organization->status->badgeColor()">{{ $organization->status->label() }}</x-badge></dd>
                </div>
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-ink-soft">Ketua</dt>
                    <dd>{{ $organization->leader_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2 last:pb-0">
                    <dt class="text-ink-soft">Status hukum</dt>
                    <dd><x-badge :tone="$organization->legal_status->badgeColor()">{{ $organization->legal_status->label() }}</x-badge></dd>
                </div>
            </dl>
        </x-card>
    </div>
</x-layouts.app>
