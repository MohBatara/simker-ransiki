@use('App\Http\Requests\Admin\InstitutionTypePhotoRequest')

<x-layouts.app :title="'Foto '.$type->name" description="Foto kartu jenis kelembagaan di beranda. Opsional." :back="route('admin.master.items', 'jenis-kelembagaan')" focus>
    <div class="space-y-5">
        <x-card title="Foto saat ini">
            @if ($current)
                <div class="space-y-4">
                    <img src="{{ $current['webp'][640] }}" alt="Foto {{ $type->name }} saat ini" class="aspect-[4/3] w-full max-w-sm rounded-card bg-subtle object-cover">
                    <form method="POST" action="{{ route('admin.master.type-photo.destroy', $type) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus foto</x-button>
                    </form>
                </div>
            @else
                <p class="text-ui text-ink-soft">Belum ada foto. Kartu {{ $type->name }} di beranda tampil dengan warna polos.</p>
            @endif
        </x-card>

        <x-card :title="$current ? 'Ganti foto' : 'Unggah foto'" description="Di kartu kecil, foto mengisi sisi kanan dan memudar ke warna kartu. Di kartu utama, foto memenuhi kartu.">
            <form
                method="POST"
                action="{{ route('admin.master.type-photo.store', $type) }}"
                enctype="multipart/form-data"
                class="space-y-5"
                x-data="{ preview: null, width: null, pick(event) { const file = event.target.files[0]; this.width = null; this.preview = file ? URL.createObjectURL(file) : null } }"
            >
                @csrf
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <x-field label="Foto" for="photo" hint="JPG, PNG, atau WebP. Maksimal 8 MB, lebar minimal 800 px. Foto diperkecil dan data lokasinya dibuang otomatis.">
                        <x-input type="file" name="photo" accept="image/jpeg,image/png,image/webp" x-on:change="pick($event)" required />
                    </x-field>
                    <div class="space-y-2">
                        <p class="text-ui font-semibold text-ink">Pratinjau</p>
                        <div class="aspect-[4/3] overflow-hidden rounded-control bg-subtle">
                            <template x-if="preview">
                                <img x-bind:src="preview" alt="" class="size-full object-cover" x-on:load="width = $el.naturalWidth">
                            </template>
                            <p x-show="! preview" class="flex size-full items-center justify-center px-4 text-center text-caption text-ink-muted">Pilih foto untuk melihat pratinjau.</p>
                        </div>
                        <p x-cloak x-show="width && width < {{ InstitutionTypePhotoRequest::MIN_WIDTH }}" class="text-caption text-fix">
                            Foto ini lebarnya <span x-text="width"></span> px, minimal 800 px. Pilih foto yang lebih besar.
                        </p>
                    </div>
                </div>
                @if ($errors->any())
                    <x-alert tone="fix" title="Foto belum disimpan" data-form-errors>{{ $errors->first('photo') }}</x-alert>
                @endif
                <x-button type="submit" processing="Menyimpan…">Simpan foto</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
