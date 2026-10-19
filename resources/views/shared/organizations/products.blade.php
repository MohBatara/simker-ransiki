{{--
    Daftar produk kelompok dengan tambah/ubah/hapus lewat modal.
    Variabel: $products, $canManage, $canFeature (penanda unggulan, khusus admin), $canPhoto (unggah foto),
    $urls [store, update, destroy] dengan __ID__.
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
    {{-- Tombol "Tambah produk" ada di header halaman (x-slot:actions) dan mengirim event ini. --}}
    x-on:product-create.window="create()"
    class="space-y-4"
>
    @if ($products->isEmpty())
        <x-admin.empty title="Belum ada produk" description="Tambahkan produk yang dihasilkan kelompok ini agar bisa dicatat di laporan bulanan.">
            @if ($canManage)
                <x-button icon="plus" x-on:click="create()">Tambah produk</x-button>
            @endif
        </x-admin.empty>
    @else
        <x-admin.table>
            <x-slot:head>
                <x-admin.table.heading>Nama</x-admin.table.heading>
                <x-admin.table.heading>Satuan</x-admin.table.heading>
                <x-admin.table.heading optional>Kapasitas</x-admin.table.heading>
                <x-admin.table.heading>Tampil publik</x-admin.table.heading>
                <x-admin.table.heading>Unggulan</x-admin.table.heading>
                @if ($canManage)
                    <x-admin.table.heading><span class="sr-only">Tindakan</span></x-admin.table.heading>
                @endif
            </x-slot:head>

            @foreach ($products as $product)
                <x-admin.table.row>
                    <x-admin.table.cell primary>
                        <span class="flex items-center gap-3">
                            @if ($product->photo_path)
                                <img src="{{ $product->photoUrl(thumbnail: true) }}" alt="" loading="lazy" class="size-10 shrink-0 rounded-control border border-line object-cover">
                            @else
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-control border border-line bg-subtle text-ink-muted" aria-hidden="true">
                                    <x-heroicon-o-photo class="size-5" />
                                </span>
                            @endif
                            <span>{{ $product->name }}</span>
                        </span>
                    </x-admin.table.cell>
                    <x-admin.table.cell label="Satuan">{{ $product->unit ?? \App\Support\Format::orEmpty(null) }}</x-admin.table.cell>
                    <x-admin.table.cell label="Kapasitas" optional>{{ $product->capacity ?? \App\Support\Format::orEmpty(null) }}</x-admin.table.cell>
                    <x-admin.table.cell label="Tampil publik">{{ $product->is_public ? 'Ya' : 'Tidak' }}</x-admin.table.cell>
                    <x-admin.table.cell label="Unggulan">{{ $product->is_featured ? 'Ya' : 'Tidak' }}</x-admin.table.cell>
                    @if ($canManage)
                        <x-admin.table.actions :for="$product->name">
                            <x-dropdown.item icon="pencil-square" x-on:click="edit({{ Js::from($product->only(['id', ...$fields])) }})">Ubah</x-dropdown.item>
                            @if ($canPhoto ?? false)
                                <x-dropdown.item icon="camera" x-on:click="$dispatch('product-photo', {{ Js::from([
                                    'name' => $product->name,
                                    'url' => $product->photoUrl(),
                                    'uploadUrl' => route('product-photos.store', $product),
                                    'deleteUrl' => route('product-photos.destroy', $product),
                                ]) }})">{{ $product->photo_path ? 'Ganti foto' : 'Tambah foto' }}</x-dropdown.item>
                            @endif
                            <x-dropdown.item icon="trash" class="text-fix!" x-on:click="remove({{ Js::from(['id' => $product->id, 'name' => $product->name]) }})">Hapus</x-dropdown.item>
                        </x-admin.table.actions>
                    @endif
                </x-admin.table.row>
            @endforeach
        </x-admin.table>
    @endif

    @if ($canManage)
        <x-modal name="record-form" :show="$failed" x-on:close="closed()">
            <x-slot:title>
                <span x-text="mode === 'edit' ? 'Ubah produk' : 'Tambah produk'">Tambah produk</span>
            </x-slot:title>

            <form method="POST" x-bind:action="action" id="product-form" class="space-y-4 text-ink">
                @csrf
                <p class="text-label text-ink-3">Kolom bertanda <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi.</p>
                <input type="hidden" name="_method" x-bind:value="mode === 'edit' ? 'PUT' : 'POST'">
                <input type="hidden" name="_modal" value="record-form">
                <input type="hidden" name="_id" x-bind:value="record.id">

                <x-field label="Nama produk" for="name" required>
                    <x-input name="name" x-model="record.name" maxlength="255" autofocus required />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Satuan" for="unit" hint="Pilih Lainnya bila satuannya tidak ada di daftar." optional>
                        <x-unit-input name="unit" model="record.unit" id="unit" />
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
                <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'record-form')">Batal</x-button>
                <x-button type="submit" form="product-form" processing="Menyimpan…">Simpan</x-button>
            </x-slot:footer>
        </x-modal>

        @if ($canPhoto ?? false)
            <div x-data="{ photo: {} }" x-on:product-photo.window="photo = $event.detail; $dispatch('open-modal', 'product-photo')">
                <x-modal name="product-photo" title="Foto produk">
                    <p class="font-semibold text-ink" x-text="photo.name"></p>
                    <template x-if="photo.url">
                        <img x-bind:src="photo.url" alt="Foto produk saat ini" class="mt-3 max-h-64 w-full rounded-tile border border-line object-contain">
                    </template>
                    <p x-show="! photo.url" class="mt-1">Belum ada foto. Foto tampil di halaman publik bila produk ditampilkan.</p>
                    <div class="mt-4">
                        <x-file-upload
                            kind="photo"
                            :multiple="false"
                            :remaining="1"
                            camera
                            x-bind:data-url="photo.uploadUrl"
                            hint="Foto JPG atau PNG, maksimal 10 MB. Foto lama akan diganti."
                        />
                    </div>
                    <x-slot:footer>
                        <form method="POST" x-bind:action="photo.deleteUrl" x-show="photo.url" class="mr-auto">
                            @csrf
                            @method('DELETE')
                            <x-button variant="danger" type="submit" processing="Menghapus…">Hapus foto</x-button>
                        </form>
                        <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'product-photo')">Tutup</x-button>
                    </x-slot:footer>
                </x-modal>
            </div>
        @endif

        <x-admin.confirm name="record-delete" title="Hapus produk" bind-action="deleteAction" method="DELETE" confirm="Hapus" processing="Menghapus…">
            <p>Hapus <strong class="text-ink" x-text="deleting.name"></strong>? Laporan lama yang mencatat produk ini tetap menyimpan namanya.</p>
        </x-admin.confirm>
    @endif
</div>
