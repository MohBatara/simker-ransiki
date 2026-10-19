{{--
    Form tambah/ubah slide hero. Variabel: $slide (null = tambah). Isian berawalan sendiri per form
    (HeroSlideRequest::prefixFor) agar error dan isian lama tidak tercampur dengan form slide lain.
--}}
@use('App\Enums\HeroFocus')
@use('App\Http\Requests\Admin\HeroSlideRequest')
@use('App\Support\PublicImages')

@php
    $p = HeroSlideRequest::prefixFor($slide);
    $current = $slide ? PublicImages::hero($slide)['webp'][640] : null;
    $focus = old("{$p}.focus", $slide?->focus->value ?? HeroFocus::Center->value);
    $formErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, $p.'.'));
@endphp

<form novalidate
    method="POST"
    action="{{ $slide ? route('admin.hero-slides.update', $slide) : route('admin.hero-slides.store') }}"
    enctype="multipart/form-data"
    class="space-y-5"
    x-data="{
        preview: @js($current),
        focus: @js($focus),
        width: null,
        pick(event) {
            const file = event.target.files[0];
            this.width = null;
            this.preview = file ? URL.createObjectURL(file) : @js($current);
        },
    }"
>
    @csrf

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="space-y-5">
            <x-field label="Foto" :for="$p.'[photo]'" :optional="(bool) $slide" :required="! $slide" hint="Foto diperkecil dan data lokasinya dibuang otomatis.">
                <x-admin.dropzone :name="$p.'[photo]'" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WebP. Maksimal 8 MB, lebar minimal 1.280 px (disarankan 1.600 px)." :preview="false" x-on:change="pick($event)" />
            </x-field>
            <x-field label="Teks alternatif" :for="$p.'[alt_text]'" hint="Jelaskan isi foto untuk pembaca layar, mis. Nelayan menarik jaring di perahu kayu." required>
                <x-input :name="$p.'[alt_text]'" :value="$slide?->alt_text" maxlength="200" required />
            </x-field>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Keterangan singkat" :for="$p.'[caption]'" hint="Tampil kecil di pojok foto, mis. Bidang usaha perikanan." optional>
                    <x-input :name="$p.'[caption]'" :value="$slide?->caption" maxlength="100" />
                </x-field>
                <x-field label="Posisi fokus foto" :for="$p.'[focus]'" hint="Bagian foto yang dipertahankan saat foto dipotong.">
                    <x-select :name="$p.'[focus]'" :options="HeroFocus::options()" :selected="$focus" x-model="focus" />
                </x-field>
            </div>
            <x-checkbox :name="$p.'[is_active]'" :checked="$slide?->is_active ?? true">Tampilkan di beranda</x-checkbox>
        </div>

        <div class="space-y-2">
            <p class="text-ui font-semibold text-ink">Pratinjau</p>
            <div class="aspect-[16/9] overflow-hidden rounded-tile bg-subtle">
                <template x-if="preview">
                    <img
                        x-bind:src="preview"
                        alt=""
                        class="size-full object-cover"
                        x-bind:class="{ 'object-top': focus === 'top', 'object-center': focus === 'center', 'object-bottom': focus === 'bottom' }"
                        x-on:load="width = $el.naturalWidth"
                    >
                </template>
                <p x-show="! preview" class="flex size-full items-center justify-center px-4 text-center text-caption text-ink-muted">Pilih foto untuk melihat pratinjau.</p>
            </div>
            <p x-cloak x-show="width && width < {{ HeroSlideRequest::MIN_WIDTH }} && preview !== @js($current)" class="text-caption text-fix">
                Foto ini lebarnya <span x-text="width"></span> px, minimal 1.280 px. Pilih foto yang lebih besar.
            </p>
            <p x-cloak x-show="width >= {{ HeroSlideRequest::MIN_WIDTH }} && width < {{ HeroSlideRequest::SHARP_WIDTH }} && preview !== @js($current)" class="rounded-control bg-wait-bg px-2 py-1 text-caption text-wait">
                Foto mungkin kurang tajam di layar lebar (lebarnya <span x-text="width"></span> px). Tetap bisa disimpan.
            </p>
        </div>
    </div>

    @if ($formErrors->isNotEmpty())
        {{-- data-form-errors: saat halaman terbuka, fokus (dan gulir) ke isian pertama yang salah. --}}
        <x-alert tone="fix" :title="'Slide belum disimpan. Ada '.$formErrors->count().' isian yang perlu diperbaiki'" data-form-errors>
            <ul class="mt-1 space-y-1">
                @foreach ($formErrors as $key => $messages)
                    <li><a href="#{{ App\Support\FormField::id($key) }}" class="rounded-control underline underline-offset-2 hover:no-underline">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
            <p class="mt-2">Isian lain tetap tersimpan di form. Pilih ulang fotonya, karena browser tidak menyimpan file setelah form dikirim.</p>
        </x-alert>
    @endif

    <div class="flex flex-wrap gap-3">
        <x-button type="submit" processing="Menyimpan…">{{ $slide ? 'Simpan slide' : 'Tambah slide' }}</x-button>
        @if ($slide)
            <x-button variant="secondary" x-on:click="open = false">Batal</x-button>
        @endif
    </div>
</form>
