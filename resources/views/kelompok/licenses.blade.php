<x-layouts.app title="Legalitas & izin" description="Status hukum dan izin usaha kelompok.">
    @can('create', [App\Models\License::class, $organization])
        <x-slot:actions>
            <x-button icon="plus" x-data x-on:click="$dispatch('license-create')">Tambah izin</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        <form novalidate method="POST" action="{{ route('kelompok.legal.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-admin.form-intro />
            <x-admin.form-section title="Status legalitas" description="Status hukum kelompok, mis. sudah berbadan hukum atau masih dalam proses.">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Status hukum" for="legal_status" required>
                        <x-select name="legal_status" :options="$options['legalStatuses']" :selected="$organization->legal_status->value" required />
                    </x-field>
                    <x-field label="Nomor legalitas" for="legal_number" hint="Isi bila sudah terdaftar, mis. nomor akta atau SK." optional>
                        <x-input name="legal_number" :value="$organization->legal_number" maxlength="255" />
                    </x-field>
                    <x-field label="Catatan" for="legal_notes" class="md:col-span-2" optional>
                        <x-textarea name="legal_notes" rows="2" :value="$organization->legal_notes" />
                    </x-field>
                </div>
            </x-admin.form-section>
            <x-admin.form-bar :cancel="route('kelompok.licenses.index')" submit="Simpan status legalitas" />
        </form>

        <div class="space-y-3">
            <h2 class="text-body font-semibold text-ink">Daftar izin</h2>
            @include('shared.organizations.licenses', [
                'canManage' => auth()->user()->can('create', [App\Models\License::class, $organization]),
                'licenseUrl' => route('kelompok.licenses.show', '__ID__'),
                'urls' => [
                    'store' => route('kelompok.licenses.store'),
                    'update' => route('kelompok.licenses.update', '__ID__'),
                    'destroy' => route('kelompok.licenses.destroy', '__ID__'),
                ],
            ])
        </div>
    </div>
</x-layouts.app>
