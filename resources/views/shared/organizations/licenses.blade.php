{{--
    Daftar izin kelompok dengan tambah/ubah/hapus lewat modal.
    Variabel: $licenses (dengan attachments_count), $licenseTypes (saran isian), $canManage,
    $urls [store, update, destroy] dengan __ID__, $licenseUrl (halaman detail izin, dengan __ID__).
--}}
@use('App\Support\Format')

@php
    $fields = ['type', 'number', 'status', 'submitted_on', 'issued_on', 'expires_on', 'notes'];
    $failed = old('_modal') === 'record-form';
    $initial = $failed ? ['id' => old('_id')] + collect($fields)->mapWithKeys(fn ($field) => [$field => old($field)])->all() : null;
    $toRecord = fn ($license) => [
        'id' => $license->id,
        'type' => $license->type,
        'number' => $license->number,
        'status' => $license->status->value,
        'submitted_on' => $license->submitted_on?->toDateString(),
        'issued_on' => $license->issued_on?->toDateString(),
        'expires_on' => $license->expires_on?->toDateString(),
        'notes' => $license->notes,
    ];
@endphp

<div
    x-data="recordForm(@js([
        'storeUrl' => $urls['store'],
        'updateUrl' => $urls['update'],
        'destroyUrl' => $urls['destroy'],
        'initial' => $initial,
        'hadErrors' => $errors->any(),
    ]))"
    {{-- Tombol "Tambah izin" ada di header halaman (x-slot:actions) dan mengirim event ini. --}}
    x-on:license-create.window="create(); record.status = 'none'"
    class="space-y-4"
>
    @if ($licenses->isEmpty())
        <x-empty-state title="Belum ada izin" description="Catat izin usaha kelompok, termasuk yang masih dalam proses.">
            @if ($canManage)
                <x-button icon="plus" x-on:click="create(); record.status = 'none'">Tambah izin</x-button>
            @endif
        </x-empty-state>
    @else
        <x-table clickable>
            <x-slot:head>
                <x-table.heading>Jenis izin</x-table.heading>
                <x-table.heading>Nomor</x-table.heading>
                <x-table.heading>Status</x-table.heading>
                <x-table.heading optional>Diajukan</x-table.heading>
                <x-table.heading>Terbit</x-table.heading>
                <x-table.heading numeric optional>Lampiran</x-table.heading>
                @if ($canManage)
                    <x-table.heading><span class="sr-only">Aksi</span></x-table.heading>
                @endif
            </x-slot:head>

            @foreach ($licenses as $license)
                <x-table.row :href="str_replace('__ID__', $license->id, $licenseUrl)">
                    <x-table.cell primary>{{ $license->type }}</x-table.cell>
                    <x-table.cell label="Nomor">{{ $license->number ?? '—' }}</x-table.cell>
                    <x-table.cell badge><x-badge :tone="$license->status->badgeColor()">{{ $license->status->label() }}</x-badge></x-table.cell>
                    <x-table.cell label="Diajukan" optional>{{ Format::date($license->submitted_on) }}</x-table.cell>
                    <x-table.cell label="Terbit">{{ Format::date($license->issued_on) }}</x-table.cell>
                    <x-table.cell label="Lampiran" numeric optional>{{ Format::number($license->attachments_count) }}</x-table.cell>
                    @if ($canManage)
                        <x-table.cell actions>
                            {{-- relative z-10: tombol tetap bisa diklik di atas tautan baris. --}}
                            <div class="relative z-10 inline-flex items-center justify-end gap-2 max-md:w-full">
                                <x-button variant="ghost" x-on:click="remove({{ Js::from(['id' => $license->id, 'name' => $license->type]) }})">Hapus</x-button>
                                <x-button variant="secondary" x-on:click="edit({{ Js::from($toRecord($license)) }})">Ubah</x-button>
                            </div>
                        </x-table.cell>
                    @endif
                </x-table.row>
            @endforeach
        </x-table>
    @endif

    @if ($canManage)
        <x-modal name="record-form" :show="$failed" x-on:close="closed()">
            <x-slot:title>
                <span x-text="mode === 'edit' ? 'Ubah izin' : 'Tambah izin'">Tambah izin</span>
            </x-slot:title>

            <form method="POST" x-bind:action="action" id="license-form" class="space-y-4 text-ink">
                @csrf
                <input type="hidden" name="_method" x-bind:value="mode === 'edit' ? 'PUT' : 'POST'">
                <input type="hidden" name="_modal" value="record-form">
                <input type="hidden" name="_id" x-bind:value="record.id">

                <x-field label="Jenis izin" for="type" hint="Pilih dari saran atau ketik jenis lain.">
                    <x-input name="type" list="license-types" x-model="record.type" maxlength="100" autofocus required />
                    <datalist id="license-types">
                        @foreach ($licenseTypes as $type)
                            <option value="{{ $type }}"></option>
                        @endforeach
                    </datalist>
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Status" for="status">
                        <x-select name="status" :options="App\Enums\LicenseStatus::options()" x-model="record.status" required />
                    </x-field>
                    <x-field label="Nomor izin" for="number" optional>
                        <x-input name="number" x-model="record.number" maxlength="100" />
                    </x-field>
                    <x-field label="Tanggal diajukan" for="submitted_on" optional>
                        <x-input name="submitted_on" type="date" x-model="record.submitted_on" />
                    </x-field>
                    <x-field label="Tanggal terbit" for="issued_on" optional>
                        <x-input name="issued_on" type="date" x-model="record.issued_on" />
                    </x-field>
                    <x-field label="Berlaku sampai" for="expires_on" optional hint="Kosongkan bila tidak ada batas atau belum diketahui.">
                        <x-input name="expires_on" type="date" x-model="record.expires_on" />
                    </x-field>
                </div>
                <x-field label="Catatan" for="notes" optional>
                    <x-textarea name="notes" rows="3" x-model="record.notes" />
                </x-field>
            </form>

            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'record-form')">Batal</x-button>
                <x-button type="submit" form="license-form" processing="Menyimpan…">Simpan</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="record-delete" title="Hapus izin">
            <p>Hapus izin <strong class="text-ink" x-text="deleting.name"></strong>? Data yang dihapus tidak bisa dikembalikan.</p>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'record-delete')">Batal</x-button>
                <form method="POST" x-bind:action="deleteAction">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit" processing="Menghapus…">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
