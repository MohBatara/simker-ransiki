@use('App\Http\Requests\Admin\HeroSlideRequest')
@use('App\Models\HeroSlide')
@use('App\Models\SiteSetting')
@use('App\Support\PublicImages')

<x-layouts.app title="Konten Beranda" description="Foto hero, judul, dan bagian lain di beranda website publik." section="website" tab="beranda">
    <div class="space-y-5">
        <x-card title="Foto hero" description="Maksimal 3 slide, bergantian setiap 7 detik di bagian atas beranda. Selama belum ada slide yang ditampilkan, beranda memakai foto bawaan.">
            @if ($slides->isEmpty())
                <p class="text-ui text-ink-soft">Belum ada slide. Tambah slide pertama di bawah.</p>
            @else
                <ol class="divide-y divide-line">
                    @foreach ($slides as $slide)
                        @php $p = HeroSlideRequest::prefixFor($slide); @endphp
                        <li class="space-y-4 py-4 first:pt-0 last:pb-0" x-data="{ open: @js($errors->has($p.'.*')) }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                <img src="{{ PublicImages::hero($slide)['webp'][640] }}" alt="" class="aspect-[16/9] w-full shrink-0 rounded-control bg-subtle object-cover sm:w-40 {{ $slide->focus->objectPosition() }}">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-ink">Slide {{ $loop->iteration }}</p>
                                        <x-admin.badge-status :tone="$slide->is_active ? 'success' : 'neutral'" :label="$slide->is_active ? 'Tampil' : 'Disembunyikan'" />
                                    </div>
                                    <p class="text-ui text-ink">{{ $slide->caption ?? 'Tanpa keterangan' }}</p>
                                    <p class="text-caption text-ink-soft">Teks alternatif: {{ $slide->alt_text }}</p>
                                    <p class="text-caption text-ink-soft">Posisi fokus: {{ $slide->focus->label() }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (['naik' => ['arrow-up', 'Naikkan', $loop->first], 'turun' => ['arrow-down', 'Turunkan', $loop->last]] as $direction => [$icon, $label, $disabled])
                                        <form method="POST" action="{{ route('admin.hero-slides.move', [$slide, $direction]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button variant="secondary" type="submit" :icon="$icon" :disabled="$disabled" aria-label="{{ $label }} slide {{ $loop->parent->iteration }}" data-no-processing />
                                        </form>
                                    @endforeach
                                    <x-button variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open">Ubah</x-button>
                                    <x-button variant="ghost" x-on:click="$dispatch('open-modal', 'hapus-slide-{{ $slide->id }}')">Hapus</x-button>
                                </div>
                            </div>
                            <div x-cloak x-show="open" class="rounded-card border border-line p-4 md:p-5">
                                @include('admin.content._slide-form', ['slide' => $slide])
                            </div>

                            <x-modal name="hapus-slide-{{ $slide->id }}" title="Hapus slide">
                                <p>Hapus slide {{ $loop->iteration }}? Fotonya ikut dihapus dan tidak bisa dikembalikan.</p>
                                <x-slot:footer>
                                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'hapus-slide-{{ $slide->id }}')">Batal</x-button>
                                    <form method="POST" action="{{ route('admin.hero-slides.destroy', $slide) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="danger" type="submit" processing="Menghapus…">Hapus</x-button>
                                    </form>
                                </x-slot:footer>
                            </x-modal>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>

        @if ($slides->count() < HeroSlide::MAX)
            <x-card title="Tambah slide" :description="'Bisa '.(HeroSlide::MAX - $slides->count()).' slide lagi.'">
                @include('admin.content._slide-form', ['slide' => null])
            </x-card>
        @else
            <x-alert>Sudah 3 slide. Hapus salah satu slide untuk menambah foto baru.</x-alert>
        @endif

        <form method="POST" action="{{ route('admin.content.update') }}">
            @csrf
            @method('PUT')
            <x-card title="Judul dan teks beranda" description="Kosongkan untuk memakai teks bawaan.">
                <div class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-field label="Judul" for="hero_title" hint="Bawaan: {{ SiteSetting::DEFAULTS['hero_title'] }}" optional>
                            <x-input name="hero_title" :value="$settings['hero_title']" :placeholder="SiteSetting::DEFAULTS['hero_title']" maxlength="80" />
                        </x-field>
                        <x-field label="Bagian judul berwarna" for="hero_title_accent" hint="Tampil kuning setelah judul. Bawaan: {{ SiteSetting::DEFAULTS['hero_title_accent'] }}" optional>
                            <x-input name="hero_title_accent" :value="$settings['hero_title_accent']" :placeholder="SiteSetting::DEFAULTS['hero_title_accent']" maxlength="60" />
                        </x-field>
                    </div>
                    <x-field label="Kalimat penjelas" for="home_intro" hint="Satu atau dua kalimat di bawah judul. Maksimal 300 karakter." optional>
                        <x-textarea name="home_intro" rows="3" maxlength="300" :value="$settings['home_intro']" :placeholder="SiteSetting::DEFAULTS['home_intro']" />
                    </x-field>
                    <x-field label="Judul bagian Tentang" for="about_title" hint="Bawaan: {{ SiteSetting::DEFAULTS['about_title'] }}" optional>
                        <x-input name="about_title" :value="$settings['about_title']" :placeholder="SiteSetting::DEFAULTS['about_title']" maxlength="80" />
                    </x-field>
                    <div class="flex flex-wrap items-center gap-4">
                        <x-button type="submit" processing="Menyimpan…">Simpan judul</x-button>
                        <x-link :href="route('home')" target="_blank" rel="noopener" class="font-normal">Lihat beranda</x-link>
                    </div>
                </div>
            </x-card>
        </form>

        <form method="POST" action="{{ route('admin.content.update') }}">
            @csrf
            @method('PUT')
            <x-card title="Alur pendaftaran" description="Empat langkah di bagian ajakan Kelompok Anda belum terdaftar? di bawah Beranda. Kosongkan untuk memakai teks bawaan.">
                <div class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach (range(1, 4) as $i)
                            <fieldset class="space-y-3 rounded-card border border-line p-4">
                                <legend class="px-1 text-ui font-semibold text-ink">Langkah {{ $i }}</legend>
                                <x-field label="Judul" :for="'how_'.$i.'_title'" optional>
                                    <x-input :name="'how_'.$i.'_title'" :value="$settings['how_'.$i.'_title']" :placeholder="SiteSetting::DEFAULTS['how_'.$i.'_title']" maxlength="60" />
                                </x-field>
                                <x-field label="Penjelasan" :for="'how_'.$i.'_text'" hint="Satu kalimat, maksimal 160 karakter." optional>
                                    <x-textarea :name="'how_'.$i.'_text'" rows="2" maxlength="160" :value="$settings['how_'.$i.'_text']" :placeholder="SiteSetting::DEFAULTS['how_'.$i.'_text']" />
                                </x-field>
                            </fieldset>
                        @endforeach
                    </div>
                    <x-button type="submit" processing="Menyimpan…">Simpan alur</x-button>
                </div>
            </x-card>
        </form>

        <x-card title="Kartu Mengenal Distrik Ransiki" description="Tiga kartu foto di bawah judul Mengenal Distrik Ransiki. Kosongkan isian untuk memakai teks bawaan; tanpa foto, kartu memakai foto bidang usaha tujuan.">
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach (range(1, App\Support\AboutCards::COUNT) as $n)
                    @php
                        $p = App\Http\Requests\Admin\AboutCardRequest::prefixFor($n);
                        $current = App\Support\AboutCards::photo($n, $settings);
                        $cardErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, $p.'.'));
                    @endphp
                    <form
                        method="POST"
                        action="{{ route('admin.about-cards.update', $n) }}"
                        enctype="multipart/form-data"
                        class="space-y-4 rounded-card border border-line p-4"
                        x-data="{ preview: @js($current['webp'][640] ?? null), pick(event) { const file = event.target.files[0]; this.preview = file ? URL.createObjectURL(file) : @js($current['webp'][640] ?? null) } }"
                    >
                        @csrf
                        <p class="font-semibold text-ink">Kartu {{ $n }}</p>
                        <div class="aspect-[4/5] max-h-56 overflow-hidden rounded-control bg-subtle">
                            <template x-if="preview"><img x-bind:src="preview" alt="" class="size-full object-cover"></template>
                            <p x-show="! preview" class="flex size-full items-center justify-center px-4 text-center text-caption text-ink-muted">Tanpa foto: memakai foto bidang usaha.</p>
                        </div>
                        <x-field label="Foto" :for="$p.'[photo]'" hint="JPG, PNG, atau WebP. Maksimal 8 MB, lebar minimal 800 px." optional>
                            <x-input type="file" :name="$p.'[photo]'" accept="image/jpeg,image/png,image/webp" x-on:change="pick($event)" />
                        </x-field>
                        <x-field label="Judul" :for="$p.'[title]'" :hint="'Bawaan: '.App\Models\SiteSetting::DEFAULTS['about_card_'.$n.'_title']" optional>
                            <x-input :name="$p.'[title]'" :value="$settings['about_card_'.$n.'_title']" :placeholder="App\Models\SiteSetting::DEFAULTS['about_card_'.$n.'_title']" maxlength="60" />
                        </x-field>
                        <x-field label="Kalimat" :for="$p.'[text]'" hint="Maksimal 90 karakter." optional>
                            <x-textarea :name="$p.'[text]'" rows="3" maxlength="90" :value="$settings['about_card_'.$n.'_text']" :placeholder="App\Models\SiteSetting::DEFAULTS['about_card_'.$n.'_text']" />
                        </x-field>
                        <x-field label="Bidang usaha tujuan" :for="$p.'[sector]'" :hint="'Bawaan: '.App\Models\SiteSetting::ABOUT_CARD_SECTORS[$n]" optional>
                            <x-select :name="$p.'[sector]'" :options="$sectorOptions" :selected="$settings['about_card_'.$n.'_sector']" placeholder="Pakai bawaan" />
                        </x-field>
                        @if ($cardErrors->isNotEmpty())
                            <x-alert tone="fix" title="Kartu belum disimpan" data-form-errors>{{ $cardErrors->first()[0] }} Pilih ulang fotonya bila perlu.</x-alert>
                        @endif
                        <x-button type="submit" processing="Menyimpan…">Simpan kartu {{ $n }}</x-button>
                    </form>
                @endforeach
            </div>
            @foreach (range(1, App\Support\AboutCards::COUNT) as $n)
                @if ($settings['about_card_'.$n.'_photo_path'])
                    <form method="POST" action="{{ route('admin.about-cards.photo.destroy', $n) }}" class="mt-3 inline-block">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus foto kartu {{ $n }}</x-button>
                    </form>
                @endif
            @endforeach
        </x-card>
    </div>
</x-layouts.app>
