@use('App\Http\Requests\Admin\HeroSlideRequest')
@use('App\Models\HeroSlide')
@use('App\Models\SiteSetting')
@use('App\Support\PublicImages')

<x-layouts.app title="Konten Website" description="Teks dan foto yang tampil di website publik." :back="route('admin.dashboard')" focus>
    <div class="space-y-5">
        @include('admin.content._tabs', ['active' => 'beranda'])

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
                                        <x-badge :tone="$slide->is_active ? 'ok' : 'idle'">{{ $slide->is_active ? 'Tampil' : 'Disembunyikan' }}</x-badge>
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
            <x-card title="Judul dan pengantar" description="Kosongkan untuk memakai teks bawaan.">
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
                    <div class="flex flex-wrap items-center gap-4">
                        <x-button type="submit" processing="Menyimpan…">Simpan judul</x-button>
                        <x-link :href="route('home')" target="_blank" rel="noopener" class="font-normal">Lihat beranda</x-link>
                    </div>
                </div>
            </x-card>
        </form>

        @php $about = $settings['about_photo_path']; @endphp
        <x-card title="Foto bagian Tentang Distrik Ransiki" description="Opsional. Tampil di samping ringkasan profil di beranda. Bila kosong, dipakai foto sampul (tab Halaman & kontak).">
            <div class="space-y-4">
                @if ($about)
                    <img src="{{ route('public.site-photo', ['slot' => 'tentang', 'ukuran' => 960, 'format' => 'webp', 'v' => substr(md5($about), 0, 8)]) }}" alt="Foto bagian Tentang saat ini" class="aspect-[4/3] w-full max-w-md rounded-card object-cover">
                    <form method="POST" action="{{ route('admin.content.photo.destroy', 'tentang') }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus foto</x-button>
                    </form>
                @endif
                <x-file-upload :url="route('admin.content.photo.store', 'tentang')" kind="photo" :multiple="false" :remaining="1" hint="Foto JPG atau PNG, maksimal 10 MB. Foto diperkecil dan data lokasinya dibuang otomatis. Foto lama akan diganti." />
            </div>
        </x-card>
    </div>
</x-layouts.app>
