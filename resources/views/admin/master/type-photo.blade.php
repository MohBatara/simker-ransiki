@use('App\Http\Requests\Admin\InstitutionTypePhotoRequest')

<x-layouts.app
    :title="'Foto '.$type->name"
    description="Foto kartu jenis kelembagaan di beranda. Opsional."
    :back="route('admin.master.items', 'jenis-kelembagaan')"
    :breadcrumbs="[['label' => 'Pengaturan', 'href' => route('admin.users.index')], ['label' => 'Data master', 'href' => route('admin.master.items', 'jenis-kelembagaan')], ['label' => 'Foto '.$type->name]]"
    focus
>
    <form
        method="POST"
        action="{{ route('admin.master.type-photo.store', $type) }}"
        enctype="multipart/form-data"
        class="space-y-6"
        x-data="{ preview: null, width: null, pick(event) { const file = event.target.files[0]; this.width = null; this.preview = file ? URL.createObjectURL(file) : null } }"
    >
        @csrf
        <x-form-errors />

        <x-admin.form-section title="Foto saat ini" description="Tanpa foto, kartu jenis ini di beranda tampil dengan warna polos.">
            @if ($current)
                <img src="{{ $current['webp'][640] }}" alt="Foto {{ $type->name }} saat ini" class="aspect-[4/3] w-full max-w-sm rounded-tile bg-subtle object-cover">
                <x-button variant="danger" x-on:click="$dispatch('open-modal', 'hapus-foto-jenis')">Hapus foto</x-button>
            @else
                <p class="text-ui text-ink-soft">Belum ada foto.</p>
            @endif
        </x-admin.form-section>

        <x-admin.form-section :title="$current ? 'Ganti foto' : 'Unggah foto'" description="Di kartu kecil, foto mengisi sisi kanan dan memudar ke warna kartu. Di kartu utama, foto memenuhi kartu.">
            <x-field label="Foto" for="photo" required>
                <x-admin.dropzone name="photo" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WebP. Maksimal 8 MB, lebar minimal 800 px." :preview="false" x-on:change="pick($event)" required />
            </x-field>
            <div class="max-w-sm space-y-2" x-cloak x-show="preview">
                <p class="text-ui font-semibold text-ink">Pratinjau</p>
                <div class="aspect-[4/3] overflow-hidden rounded-tile bg-subtle">
                    <template x-if="preview">
                        <img x-bind:src="preview" alt="" class="size-full object-cover" x-on:load="width = $el.naturalWidth">
                    </template>
                </div>
                <p x-cloak x-show="width && width < {{ InstitutionTypePhotoRequest::MIN_WIDTH }}" class="text-caption text-fix">
                    Foto ini lebarnya <span x-text="width"></span> px, minimal 800 px. Pilih foto yang lebih besar.
                </p>
            </div>
            <p class="text-caption text-ink-muted">Foto diperkecil dan data lokasinya dibuang otomatis.</p>
        </x-admin.form-section>

        <x-admin.form-bar :cancel="route('admin.master.items', 'jenis-kelembagaan')" submit="Simpan foto" />
    </form>

    @if ($current)
        <x-admin.confirm name="hapus-foto-jenis" title="Hapus foto" :action="route('admin.master.type-photo.destroy', $type)" method="DELETE" confirm="Hapus" processing="Menghapus…">
            <p>Hapus foto kartu <strong class="text-ink">{{ $type->name }}</strong>? Kartu kembali tampil dengan warna polos.</p>
        </x-admin.confirm>
    @endif
</x-layouts.app>
