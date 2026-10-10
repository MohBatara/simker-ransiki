<x-layouts.app title="Legalitas & Izin" :description="$organization->name">
    <div class="space-y-6">
        <x-card title="Status legalitas" description="Status hukum kelompok, mis. sudah berbadan hukum atau masih dalam proses.">
            <form method="POST" action="{{ route('kelompok.legal.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-4 md:grid-cols-2">
                    <x-field label="Status hukum" for="legal_status">
                        <x-select name="legal_status" :options="$options['legalStatuses']" :selected="$organization->legal_status->value" required />
                    </x-field>
                    <x-field label="Nomor legalitas" for="legal_number" hint="Isi bila sudah terdaftar, mis. nomor akta atau SK." optional>
                        <x-input name="legal_number" :value="$organization->legal_number" maxlength="255" />
                    </x-field>
                    <x-field label="Catatan" for="legal_notes" class="md:col-span-2" optional>
                        <x-textarea name="legal_notes" rows="2" :value="$organization->legal_notes" />
                    </x-field>
                </div>
                <x-form-actions>
                    <x-button type="submit" processing="Menyimpan…">Simpan status legalitas</x-button>
                </x-form-actions>
            </form>
        </x-card>

        <div class="space-y-3">
            <h2 class="text-card-title font-semibold">Daftar izin</h2>
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
