@php
    $fields = $type->fields();
    $columns = collect($fields)->filter(fn (array $field) => $field['column'] ?? false);
    $failed = old('_modal') === 'record-form';
    $initial = $failed ? ['id' => old('_id')] + collect($fields)->mapWithKeys(fn ($field, $column) => [$column => old($column)])->all() : null;
    $tabs = collect(\App\Enums\MasterDataType::cases());
@endphp

<x-layouts.app title="Data Master" description="Daftar pilihan yang dipakai di profil kelompok dan akun OPD.">
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
        class="space-y-5"
    >
        <x-tabs
            :tabs="$tabs->mapWithKeys(fn ($tab) => [$tab->value => $tab->label()])->all()"
            :links="$tabs->mapWithKeys(fn ($tab) => [$tab->value => route('admin.master.items', $tab)])->all()"
            :active="$type->value"
        />

        <form method="GET" action="{{ route('admin.master.items', $type) }}" class="flex flex-wrap gap-2" role="search">
            <label for="q" class="sr-only">Cari {{ $type->noun() }}</label>
            <x-input name="q" :value="$search" placeholder="Cari {{ $type->noun() }}" class="min-w-0 flex-1 md:max-w-sm" />
            <x-button variant="secondary" type="submit">Cari</x-button>
            @if ($search !== '')
                <x-button variant="ghost" :href="route('admin.master.items', $type)">Hapus pencarian</x-button>
            @endif
        </form>

        @if ($items->isEmpty())
            @if ($search !== '')
                <x-empty-state title="Tidak ada hasil" :description="'Tidak ada '.$type->noun().' dengan kata “'.$search.'”. Periksa ejaan atau hapus pencarian.'" />
            @else
                <x-empty-state title="Belum ada {{ $type->noun() }}" description="Tambahkan {{ $type->noun() }} pertama agar bisa dipilih di profil kelompok.">
                    <x-button icon="plus" x-on:click="create()">Tambah {{ $type->noun() }}</x-button>
                </x-empty-state>
            @endif
        @else
            <x-table>
                <x-slot:head>
                    <x-table.heading>Nama</x-table.heading>
                    @foreach ($columns as $field)
                        <x-table.heading>{{ $field['label'] }}</x-table.heading>
                    @endforeach
                    <x-table.heading numeric>Kelompok</x-table.heading>
                    <x-table.heading><span class="sr-only">Aksi</span></x-table.heading>
                </x-slot:head>

                @foreach ($items as $item)
                    @php $usage = $type->usage($item); @endphp
                    <x-table.row>
                        <x-table.cell primary>{{ $item->name }}</x-table.cell>
                        @foreach ($columns as $column => $field)
                            <x-table.cell subtitle>{{ $item->{$column} }}</x-table.cell>
                        @endforeach
                        <x-table.cell label="Kelompok" numeric>{{ \App\Support\Format::number($item->organizations_count) }}</x-table.cell>
                        <x-table.cell actions>
                            <div class="inline-flex items-center justify-end gap-2 max-md:w-full">
                                @if ($usage)
                                    <span class="text-caption text-ink-muted">Dipakai {{ $usage }}</span>
                                @else
                                    <x-button variant="ghost" x-on:click="remove({{ Js::from(['id' => $item->id, 'name' => $item->name]) }})">Hapus</x-button>
                                @endif
                                <x-button variant="secondary" x-on:click="edit({{ Js::from($item->only(['id', ...array_keys($fields)])) }})">Ubah</x-button>
                            </div>
                        </x-table.cell>
                    </x-table.row>
                @endforeach

                @if ($items->hasPages())
                    <x-slot:footer>{{ $items->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif

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
                    <x-field :label="$field['label']" :for="$column" :hint="$field['hint'] ?? null" :optional="$field['optional'] ?? false">
                        @if ($field['textarea'] ?? false)
                            <x-textarea :name="$column" rows="3" x-model="record.{{ $column }}" :autofocus="$autofocus" />
                        @else
                            <x-input :name="$column" x-model="record.{{ $column }}" :maxlength="$field['max']" :autofocus="$autofocus" autocomplete="off" />
                        @endif
                    </x-field>
                @endforeach
            </form>

            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'record-form')">Batal</x-button>
                <x-button type="submit" form="record-form">Simpan</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="record-delete" :title="'Hapus '.$type->noun()">
            <p>Hapus <strong class="text-ink" x-text="deleting.name"></strong>? Data yang dihapus tidak bisa dikembalikan.</p>

            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'record-delete')">Batal</x-button>
                <form method="POST" x-bind:action="deleteAction">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.app>
