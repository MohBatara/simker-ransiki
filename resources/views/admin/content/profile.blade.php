@use('App\Models\SiteSetting')
@use('App\Support\PotentialBlocks')
@use('App\Http\Requests\Admin\PotentialBlockRequest')

<x-layouts.app title="Konten Website" description="Teks dan foto yang tampil di website publik." :back="route('admin.dashboard')" focus>
    <div class="space-y-5">
        @include('admin.content._tabs', ['active' => 'profil'])

        @foreach ([
            'profil' => ['Foto banner', 'Opsional. Latar banner di atas halaman Profil Ransiki dan gambar pratinjau tautan. Tanpa foto ini, banner memakai foto sampul (tab Halaman & kontak) lalu foto bawaan. Gunakan foto lanskap asli Ransiki.'],
            'kantor' => ['Foto kantor distrik', 'Opsional. Tampil di bagian Kantor distrik. Tanpa foto, bagian itu memakai gambar ikon gedung sederhana.'],
        ] as $slot => [$title, $description])
            @php $path = $settings[SiteSetting::PHOTO_SLOTS[$slot]]; @endphp
            <x-card :title="$title" :description="$description">
                <div class="space-y-4">
                    @if ($path)
                        <img src="{{ route('public.site-photo', ['slot' => $slot, 'ukuran' => 960, 'format' => 'webp', 'v' => substr(md5($path), 0, 8)]) }}" alt="{{ $title }} saat ini" class="aspect-[4/3] w-full rounded-card object-cover md:aspect-[21/9]">
                        <form method="POST" action="{{ route('admin.content.photo.destroy', $slot) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus foto</x-button>
                        </form>
                    @endif
                    <x-file-upload :url="route('admin.content.photo.store', $slot)" kind="photo" :multiple="false" :remaining="1" hint="Foto lanskap JPG atau PNG, maksimal 10 MB. Foto diperkecil dan data lokasinya dibuang otomatis. Foto lama akan diganti." />
                </div>
            </x-card>
        @endforeach

        <form method="POST" action="{{ route('admin.content.update') }}" class="space-y-5">
            @csrf
            @method('PUT')
            <x-form-errors />

            <x-card title="Banner dan pembuka" description="Bagian Sekilas bisa memakai format sederhana: ## Judul, - daftar, **tebal**, [teks](tautan). Pisahkan paragraf dengan satu baris kosong.">
                <div class="space-y-5">
                    <x-field label="Kalimat pengantar banner" for="profile_lead" :hint="'Satu kalimat di bawah judul. Bawaan: '.SiteSetting::DEFAULTS['profile_lead']" optional>
                        <x-input name="profile_lead" :value="$settings['profile_lead']" :placeholder="SiteSetting::DEFAULTS['profile_lead']" maxlength="160" />
                    </x-field>
                    <x-field label="Paragraf pembuka" for="profile_intro" hint="Tampil dengan huruf besar di awal bagian Sekilas. Teks biasa, maksimal 600 karakter." optional>
                        <x-textarea name="profile_intro" rows="3" maxlength="600" :value="$settings['profile_intro']" />
                    </x-field>
                    <x-field label="Isi profil" for="profile_body" optional>
                        <x-textarea name="profile_body" rows="12" :value="$settings['profile_body']" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Data singkat" description="Isi hanya dengan data resmi. Baris yang kosong tidak ditampilkan. Jumlah kampung dihitung otomatis dari Data Master.">
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach (SiteSetting::PROFILE_FACT_KEYS as $key => $label)
                        <x-field :label="$label" :for="$key" :hint="match ($key) { 'profile_area' => 'Mis. 1.234 km².', 'profile_population' => 'Mis. 12.345 jiwa (2025).', default => null }" optional>
                            <x-input :name="$key" :value="$settings[$key]" maxlength="80" />
                        </x-field>
                    @endforeach
                    <x-field label="Sumber data" for="profile_data_source" hint="Mis. nama dokumen dan tahunnya. Tampil kecil di bawah daftar." class="md:col-span-2" optional>
                        <x-input name="profile_data_source" :value="$settings['profile_data_source']" maxlength="200" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Potensi ekonomi" description="Kalimat di bawah judul bagian. Isi tiap blok diatur di bawah form ini.">
                <x-field label="Kalimat pengantar" for="potential_intro" :hint="'Bawaan: '.SiteSetting::DEFAULTS['potential_intro']" optional>
                    <x-textarea name="potential_intro" rows="2" maxlength="200" :value="$settings['potential_intro']" :placeholder="SiteSetting::DEFAULTS['potential_intro']" />
                </x-field>
                <p class="mt-4 text-caption">
                    <x-link :href="route('public.profile')" target="_blank" rel="noopener" class="font-normal">Lihat halaman Profil Ransiki</x-link>
                </p>
            </x-card>

            <x-form-actions sticky>
                <x-button variant="ghost" :href="route('admin.dashboard')">Batal</x-button>
                <x-button type="submit" processing="Menyimpan…">Simpan konten</x-button>
            </x-form-actions>
        </form>

        <x-card title="Blok potensi ekonomi" description="Tiga blok foto dan teks berselang seling. Kosongkan isian untuk memakai teks bawaan; tanpa foto, blok memakai foto bidang usaha. Jumlah kelompok dan tautan Lihat kelompok mengikuti bidang usaha yang dipilih.">
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach (range(1, PotentialBlocks::COUNT) as $n)
                    @php
                        $p = PotentialBlockRequest::prefixFor($n);
                        $current = PotentialBlocks::photo($n, $settings);
                        $blockErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, $p.'.'));
                    @endphp
                    <form
                        method="POST"
                        action="{{ route('admin.potential-blocks.update', $n) }}"
                        enctype="multipart/form-data"
                        class="space-y-4 rounded-card border border-line p-4"
                        x-data="{ preview: @js($current['webp'][480] ?? null), pick(event) { const file = event.target.files[0]; this.preview = file ? URL.createObjectURL(file) : @js($current['webp'][480] ?? null) } }"
                    >
                        @csrf
                        <p class="font-semibold text-ink">Blok {{ $n }}</p>
                        <div class="aspect-[4/3] overflow-hidden rounded-control bg-subtle">
                            <template x-if="preview"><img x-bind:src="preview" alt="" class="size-full object-cover"></template>
                            <p x-show="! preview" class="flex size-full items-center justify-center px-4 text-center text-caption text-ink-muted">Tanpa foto: memakai foto bidang usaha.</p>
                        </div>
                        <x-field label="Foto" :for="$p.'[photo]'" hint="JPG, PNG, atau WebP. Maksimal 8 MB, lebar minimal 800 px." optional>
                            <x-input type="file" :name="$p.'[photo]'" accept="image/jpeg,image/png,image/webp" x-on:change="pick($event)" />
                        </x-field>
                        <x-field label="Judul" :for="$p.'[title]'" :hint="'Bawaan: '.SiteSetting::DEFAULTS['potential_'.$n.'_title']" optional>
                            <x-input :name="$p.'[title]'" :value="$settings['potential_'.$n.'_title']" :placeholder="SiteSetting::DEFAULTS['potential_'.$n.'_title']" maxlength="60" />
                        </x-field>
                        <x-field label="Teks" :for="$p.'[text]'" hint="Maksimal 400 karakter." optional>
                            <x-textarea :name="$p.'[text]'" rows="5" maxlength="400" :value="$settings['potential_'.$n.'_text']" :placeholder="SiteSetting::DEFAULTS['potential_'.$n.'_text']" />
                        </x-field>
                        <x-field label="Bidang usaha" :for="$p.'[sector]'" :hint="'Bawaan: '.SiteSetting::ABOUT_CARD_SECTORS[$n]" optional>
                            <x-select :name="$p.'[sector]'" :options="$sectorOptions" :selected="$settings['potential_'.$n.'_sector']" placeholder="Pakai bawaan" />
                        </x-field>
                        @if ($blockErrors->isNotEmpty())
                            <x-alert tone="fix" title="Blok belum disimpan" data-form-errors>{{ $blockErrors->first()[0] }} Pilih ulang fotonya bila perlu.</x-alert>
                        @endif
                        <x-button type="submit" processing="Menyimpan…">Simpan blok {{ $n }}</x-button>
                    </form>
                @endforeach
            </div>
            @foreach (range(1, PotentialBlocks::COUNT) as $n)
                @if ($settings['potential_'.$n.'_photo_path'])
                    <form method="POST" action="{{ route('admin.potential-blocks.photo.destroy', $n) }}" class="mt-3 inline-block">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus foto blok {{ $n }}</x-button>
                    </form>
                @endif
            @endforeach
        </x-card>
    </div>
</x-layouts.app>
