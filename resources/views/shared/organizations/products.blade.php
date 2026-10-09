{{--
    Daftar produk kelompok dengan tambah/ubah/hapus lewat modal.
    Variabel: $products, $canManage, $canFeature (penanda unggulan, khusus admin), $urls [store, update, destroy] dengan __ID__.
--}}
@php
    $fields = ['name', 'unit', 'capacity', 'description', 'is_public', 'is_featured'];
    $failed = old('_modal') === 'record-form';
    $initial = $failed ? ['id' => old('_id')] + collect($fields)->mapWithKeys(fn ($field) => [$field => old($field)])->all() : null;
@endphp

<div
    x-data="recordForm(@js([
        'storeUrl' => $urls['store'],
        'updateUrl' => $urls['update'],
        'destroyUrl' => $urls['destroy'],
        'initial' => $initial,
        'hadErrors' => $errors->any(),
    ]))"
    class="space-y-4"
>
    @if ($products->isEmpty())
        <x-empty-state title="Belum ada produk" description="Tambahkan produk yang dihasilkan kelompok ini agar bisa dicatat di laporan bulanan.">
            @if ($canManage)
                <x-button icon="plus" x-on:click="create()">Tambah produk</x-button>
            @endif
        </x-empty-state>
    @else
        @if ($canManage)
            <div class="flex justify-end">
                <x-button icon="plus" x-on:click="create()">Tambah produk</x-button>
            </div>
        @endif

        <x-table>
            <x-slot:head>
                <x-table.heading>Nama</x-table.heading>
                <x-table.heading>Satuan</x-table.heading>
                <x-table.heading optional>Kapasitas</x-table.heading>
                <x-table.heading>Tampil publik</x-table.heading>
                <x-table.heading>Unggulan</x-table.heading>
                @if ($canManage)
                    <x-table.heading><span class="sr-only">Aksi</span></x-table.heading>
                @endif
            </x-slot:head>

            @foreach ($products as $product)
                <x-table.row>
                    <x-table.cell primary>{{ $product->name }}</x-table.cell>
                    <x-table.cell label="Satuan">{{ $product->unit ?? '—' }}</x-table.cell>
                    <x-table.cell label="Kapasitas" optional>{{ $product->capacity ?? '—' }}</x-table.cell>
                    <x-table.cell label="Tampil publik">{{ $product->is_public ? 'Ya' : 'Tidak' }}</x-table.cell>
                    <x-table.cell label="Unggulan">{{ $product->is_featured ? 'Ya' : 'Tidak' }}</x-table.cell>
                    @if ($canManage)
                        <x-table.cell actions>
                            <div class="inline-flex items-center justify-end gap-2 max-md:w-full">
                                <x-button variant="ghost" x-on:click="remove({{ Js::from(['id' => $product->id, 'name' => $product->name]) }})">Hapus</x-button>
                                <x-button variant="secondary" x-on:click="edit({{ Js::from($product->only(['id', ...$fields])) }})">Ubah</x-button>
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
                <span x-text="mode === 'edit' ? 'Ubah produk' : 'Tambah produk'">Tambah produk</span>
            </x-slot:title>

            <form method="POST" x-bind:action="action" id="product-form" class="space-y-4 text-ink">
                @csrf
                <input type="hidden" name="_method" x-bind:value="mode === 'edit' ? 'PUT' : 'POST'">
                <input type="hidden" name="_modal" value="record-form">
                <input type="hidden" name="_id" x-bind:value="record.id">

                <x-field label="Nama produk" for="name">
                    <x-input name="name" x-model="record.name" maxlength="255" autofocus required />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Satuan" for="unit" hint="Mis. kg, liter, bungkus." optional>
                        <x-input name="unit" x-model="record.unit" maxlength="30" />
                    </x-field>
                    <x-field label="Kapasitas" for="capacity" hint="Mis. 200 kg/bulan." optional>
                        <x-input name="capacity" x-model="record.capacity" maxlength="255" />
                    </x-field>
                </div>
                <x-field label="Deskripsi" for="description" optional>
                    <x-textarea name="description" rows="3" x-model="record.description" />
                </x-field>
                <x-checkbox name="is_public" x-model="record.is_public" hint="Produk tampil di halaman publik bila kelompoknya juga tampil.">Tampilkan di halaman publik</x-checkbox>
                @if ($canFeature)
                    <x-checkbox name="is_featured" x-model="record.is_featured" hint="Ditampilkan di halaman Produk Unggulan.">Produk unggulan</x-checkbox>
                @endif
            </form>

            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'record-form')">Batal</x-button>
                <x-button type="submit" form="product-form">Simpan</x-button>
            </x-slot:footer>
        </x-modal>

        <x-modal name="record-delete" title="Hapus produk">
            <p>Hapus <strong class="text-ink" x-text="deleting.name"></strong>? Laporan lama yang mencatat produk ini tetap menyimpan namanya.</p>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'record-delete')">Batal</x-button>
                <form method="POST" x-bind:action="deleteAction">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
