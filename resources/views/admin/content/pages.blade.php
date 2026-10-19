<x-layouts.app title="Halaman & kontak" description="Foto sampul, foto bidang usaha, dan kontak kantor distrik di website publik." section="website" tab="halaman">
    <x-slot:secondary>
        <x-button variant="secondary" icon="arrow-top-right-on-square" :href="route('public.contact')" target="_blank" rel="noopener">Lihat halaman Kontak</x-button>
    </x-slot:secondary>

    <x-admin.form-intro class="mb-6" />

    <x-admin.form-layout nav>
        @foreach ([
            'sampul' => ['Foto sampul', 'Latar halaman masuk dan banner dasbor, juga banner Profil Ransiki selama foto banner di tab Profil Ransiki kosong. Foto hero beranda diatur di tab Beranda. Gunakan foto lanskap asli Ransiki, bukan infografis atau gambar berisi teks.'],
        ] as $slot => [$title, $description])
            @php $path = $settings[App\Models\SiteSetting::PHOTO_SLOTS[$slot]]; @endphp
            <x-admin.form-section :title="$title" :description="$description">
                @if ($path)
                    <img src="{{ route('public.site-photo', ['slot' => $slot, 'ukuran' => 960, 'format' => 'webp', 'v' => substr(md5($path), 0, 8)]) }}" alt="{{ $title }} saat ini" class="aspect-[4/3] w-full rounded-tile object-cover md:aspect-[21/9]">
                    <form novalidate method="POST" action="{{ route('admin.content.photo.destroy', $slot) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit" processing="Menghapus…">Hapus foto</x-button>
                    </form>
                @endif
                <x-file-upload :url="route('admin.content.photo.store', $slot)" kind="photo" :multiple="false" :remaining="1" hint="Foto lanskap JPG atau PNG, maksimal 10 MB. Foto lama akan diganti; tersimpan langsung saat diunggah." />
            </x-admin.form-section>
        @endforeach

        <x-admin.form-section title="Foto bidang usaha" description="Dipakai di kartu produk unggulan yang belum punya foto. Tersimpan langsung saat diunggah.">
            <ul class="divide-y divide-line">
                @foreach ($sectors as $sector)
                    <li class="space-y-3 py-4 first:pt-0 last:pb-0" x-data="{ open: false }">
                        <div class="flex items-center gap-3">
                            @if ($sector->photo_path)
                                <img src="{{ route('public.sector-photo', ['sector' => $sector, 'ukuran' => 480, 'format' => 'webp', 'v' => substr(md5($sector->photo_path), 0, 8)]) }}" alt="" class="size-14 shrink-0 rounded-control object-cover">
                            @else
                                <span class="flex size-14 shrink-0 items-center justify-center rounded-control bg-subtle text-ink-muted" aria-hidden="true"><x-heroicon-o-photo class="size-5" /></span>
                            @endif
                            <p class="min-w-0 flex-1 font-semibold">{{ $sector->name }}</p>
                            @if ($sector->photo_path)
                                <form novalidate method="POST" action="{{ route('admin.content.sector-photo.destroy', $sector) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="danger" type="submit" processing="Menghapus…">Hapus</x-button>
                                </form>
                            @endif
                            <x-button variant="secondary" x-on:click="open = ! open" x-bind:aria-expanded="open">{{ $sector->photo_path ? 'Ganti' : 'Unggah' }}</x-button>
                        </div>
                        <div x-cloak x-show="open">
                            <x-file-upload :url="route('admin.content.sector-photo.store', $sector)" kind="photo" :multiple="false" :remaining="1" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-admin.form-section>

        <form novalidate method="POST" action="{{ route('admin.content.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-admin.form-section title="Keterangan foto sampul" description="Tampil di bawah foto sampul bila foto itu dipakai.">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Keterangan foto" for="cover_caption" hint="Mis. Panen kakao di Kampung Contoh 1." optional>
                        <x-input name="cover_caption" :value="$settings['cover_caption']" maxlength="200" />
                    </x-field>
                    <x-field label="Nama fotografer" for="cover_credit" optional>
                        <x-input name="cover_credit" :value="$settings['cover_credit']" maxlength="100" />
                    </x-field>
                </div>
            </x-admin.form-section>

            <x-admin.form-section title="Kontak kantor distrik" description="Tampil di halaman Kontak, bagian Kantor distrik di Profil Ransiki, dan bagian bawah semua halaman publik.">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Alamat" for="office_address" class="md:col-span-2" optional>
                        <x-textarea name="office_address" rows="2" :value="$settings['office_address']" />
                    </x-field>
                    <x-field label="Telepon" for="office_phone" optional>
                        <x-input name="office_phone" type="tel" inputmode="tel" :value="$settings['office_phone']" maxlength="30" placeholder="08xx xxxx xxxx" />
                    </x-field>
                    <x-field label="WhatsApp" for="office_whatsapp" hint="Mis. 0812-0000-0000; tautan WhatsApp dibuat otomatis." optional>
                        <x-input name="office_whatsapp" type="tel" inputmode="tel" :value="$settings['office_whatsapp']" maxlength="30" placeholder="08xx xxxx xxxx" />
                    </x-field>
                    <x-field label="Email" for="office_email" optional>
                        <x-input name="office_email" type="email" :value="$settings['office_email']" maxlength="255" />
                    </x-field>
                    <x-field label="Jam layanan" for="office_hours" hint="Mis. Senin sampai Jumat, pukul 08.00 sampai 15.00 WIT." optional>
                        <x-input name="office_hours" :value="$settings['office_hours']" maxlength="200" />
                    </x-field>
                </div>
            </x-admin.form-section>

            <x-admin.form-bar :cancel="url()->current()" submit="Simpan konten" />
        </form>
    </x-admin.form-layout>
</x-layouts.app>
