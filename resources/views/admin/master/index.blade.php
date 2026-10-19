@php
    $fields = $type->fields();
    $columns = collect($fields)->filter(fn (array $field) => $field['column'] ?? false);
    $failed = old('_modal') === 'record-form';
    $initial = $failed ? ['id' => old('_id')] + collect($fields)->mapWithKeys(fn ($field, $column) => [$column => old($column)])->all() : null;
    $tabs = collect(\App\Enums\MasterDataType::cases());
@endphp

<x-layouts.app title="Data master" description="Daftar pilihan yang dipakai di profil kelompok dan akun OPD." section="pengaturan" tab="master">
    <x-slot:actions>
        <x-button icon="plus" x-data x-on:click="$dispatch('master-create')">Tambah {{ $type->noun() }}</x-button>
    </x-slot:actions>

    <div
        x-data="recordForm(@js([
            'storeUrl' => route('admin.master.store', $type),
            'updateUrl' => route('admin.master.update', [$type, '__ID__']),
            'destroyUrl' => route('admin.master.destroy', [$type, '__ID__']),
            'initial' => $initial,
            'hadErrors' => $errors->any(),
        ]))"
        x-on:master-create.window="create()"
        class="space-y-6"
    >
        <x-admin.tabs
            :tabs="$tabs->mapWithKeys(fn ($tab) => [$tab->value => ['label' => $tab->label(), 'href' => route('admin.master.items', $tab)]])->all()"
            :active="$type->value"
            label="Jenis data master"
        />

        <x-admin.card flush>
        <x-admin.toolbar
            :title="'Daftar '.$type->noun()"
            :action="route('admin.master.items', $type)"
            :search-label="'Cari '.$type->noun()"
            :search="$search"
            :summary="\App\Support\Format::number($items->total()).' '.$type->noun()"
            :clear-url="$search !== '' ? route('admin.master.items', $type) : null"
        />

        {{-- Ditukar oleh x-admin.toolbar saat pencarian berubah. --}}
        <div data-swap="data-master">
            @if ($items->isEmpty())
                @if ($search !== '')
                    <x-admin.empty bare icon="magnifying-glass" title="Tidak ada hasil" :description="'Tidak ada '.$type->noun().' dengan kata “'.$search.'”. Periksa ejaan atau hapus pencarian.'">
                        <x-button variant="secondary" :href="route('admin.master.items', $type)">Hapus pencarian</x-button>
                    </x-admin.empty>
                @else
                    <x-admin.empty bare icon="rectangle-stack" :title="'Belum ada '.($type->noun())" :description="'Tambahkan '.($type->noun()).' pertama agar bisa dipilih di profil kelompok.'">
                        <x-button icon="plus" x-on:click="create()">Tambah {{ $type->noun() }}</x-button>
                    </x-admin.empty>
                @endif
            @else
                <x-admin.table bare>
                    <x-slot:head>
                        <x-admin.table.heading>Nama</x-admin.table.heading>
                        @foreach ($columns as $field)
                            <x-admin.table.heading>{{ $field['label'] }}</x-admin.table.heading>
                        @endforeach
                        <x-admin.table.heading numeric>Kelompok</x-admin.table.heading>
                        <x-admin.table.heading><span class="sr-only">Tindakan</span></x-admin.table.heading>
                    </x-slot:head>

                    @foreach ($items as $item)
                        @php $usage = $type->usage($item); @endphp
                        <x-admin.table.row>
                            <x-admin.table.cell primary>{{ $item->name }}</x-admin.table.cell>
                            @foreach ($columns as $column => $field)
                                <x-admin.table.cell subtitle>{{ $item->{$column} }}</x-admin.table.cell>
                            @endforeach
                            <x-admin.table.cell label="Kelompok" numeric>
                                {{ \App\Support\Format::number($item->organizations_count) }}
                            </x-admin.table.cell>
                            <x-admin.table.actions :for="$item->name">
                                <x-dropdown.item icon="pencil-square" x-on:click="edit({{ Js::from($item->only(['id', ...array_keys($fields)])) }})">Ubah</x-dropdown.item>
                                @if ($type === \App\Enums\MasterDataType::InstitutionType)
                                    <x-dropdown.item icon="photo" :href="route('admin.master.type-photo.edit', $item)">{{ $item->photo_path ? 'Ganti foto' : 'Tambah foto' }}</x-dropdown.item>
                                @endif
                                @if ($usage)
                                    {{-- Data master yang dipakai tidak bisa dihapus (FK restrict). --}}
                                    <p class="border-t border-line-soft px-3 py-2 text-label text-ink-3">Tidak bisa dihapus, dipakai {{ $usage }}.</p>
                                @else
                                    <x-dropdown.item icon="trash" class="text-fix!" x-on:click="remove({{ Js::from(['id' => $item->id, 'name' => $item->name]) }})">Hapus</x-dropdown.item>
                                @endif
                            </x-admin.table.actions>
                        </x-admin.table.row>
                    @endforeach

                    @if ($items->hasPages())
                        <x-slot:footer><x-admin.pagination :paginator="$items" :noun="$type->noun()" /></x-slot:footer>
                    @endif
                </x-admin.table>
            @endif
        </div>
        </x-admin.card>

        <x-modal name="record-form" :show="$failed" x-on:close="closed()">
            <x-slot:title>
                <span x-text="mode === 'edit' ? 'Ubah {{ $type->noun() }}' : 'Tambah {{ $type->noun() }}'">Tambah {{ $type->noun() }}</span>
            </x-slot:title>

            <form method="POST" x-bind:action="action" id="record-form" class="space-y-4 text-ink">
                @csrf
                <input type="hidden" name="_method" x-bind:value="mode === 'edit' ? 'PUT' : 'POST'">
                <input type="hidden" name="_modal" value="record-form">
                <input type="hidden" name="_id" x-bind:value="record.id">

                @foreach ($fields as $column => $field)
                    @php $autofocus = $loop->first; @endphp
                    <x-field :label="$field['label']" :for="$column" :hint="$field['hint'] ?? null" :optional="$field['optional'] ?? false" :required="! ($field['optional'] ?? false)">
                        @if ($field['textarea'] ?? false)
                            <x-textarea :name="$column" rows="3" x-model="record.{{ $column }}" :autofocus="$autofocus" />
                        @else
                            <x-input :name="$column" x-model="record.{{ $column }}" :maxlength="$field['max']" :autofocus="$autofocus" autocomplete="off" />
                        @endif
                    </x-field>
                @endforeach
            </form>

            <x-slot:footer>
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'record-form')">Batal</x-button>
                <x-button type="submit" form="record-form" processing="Menyimpan…">Simpan</x-button>
            </x-slot:footer>
        </x-modal>

        <x-admin.confirm name="record-delete" :title="'Hapus '.$type->noun()" bind-action="deleteAction" method="DELETE" confirm="Hapus" processing="Menghapus…">
            <p>Hapus <strong class="text-ink" x-text="deleting.name"></strong>? Data yang dihapus tidak bisa dikembalikan.</p>
        </x-admin.confirm>
    </div>
</x-layouts.app>
