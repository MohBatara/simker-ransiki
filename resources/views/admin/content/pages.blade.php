<x-layouts.app title="Halaman & kontak" description="Foto sampul, foto bidang usaha, dan kontak kantor distrik di website publik." section="website" tab="halaman">
    <div class="space-y-5">
        @foreach ([
            'sampul' => ['Foto sampul', 'Opsional. Latar halaman masuk dan banner dasbor, juga banner Profil Ransiki selama foto banner di tab Profil Ransiki kosong. Foto hero beranda diatur di tab Beranda. Gunakan foto lanskap asli Ransiki, bukan infografis atau gambar berisi teks.'],
        ] as $slot => [$title, $description])
            @php $path = $settings[App\Models\SiteSetting::PHOTO_SLOTS[$slot]]; @endphp
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

        <x-card title="Foto bidang usaha" description="Opsional. Dipakai di kartu produk unggulan yang belum punya foto, dan di banner detail kelompok.">
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
                                <form method="POST" action="{{ route('admin.content.sector-photo.destroy', $sector) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="ghost" type="submit" processing="Menghapus…">Hapus</x-button>
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
        </x-card>

        <form method="POST" action="{{ route('admin.content.update') }}" class="space-y-5">
            @csrf
            @method('PUT')
            <x-form-errors />

            <x-card title="Keterangan foto sampul">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Keterangan foto" for="cover_caption" hint="Mis. Panen kakao di Kampung Contoh 1." optional>
                        <x-input name="cover_caption" :value="$settings['cover_caption']" maxlength="200" />
                    </x-field>
                    <x-field label="Nama fotografer" for="cover_credit" optional>
                        <x-input name="cover_credit" :value="$settings['cover_credit']" maxlength="100" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Kontak kantor distrik" description="Tampil di halaman Kontak, bagian Kantor distrik di Profil Ransiki, dan bagian bawah semua halaman publik.">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Alamat" for="office_address" class="md:col-span-2" optional>
                        <x-textarea name="office_address" rows="2" :value="$settings['office_address']" />
                    </x-field>
                    <x-field label="Telepon" for="office_phone" optional>
                        <x-input name="office_phone" type="tel" inputmode="tel" :value="$settings['office_phone']" maxlength="30" />
                    </x-field>
                    <x-field label="WhatsApp" for="office_whatsapp" hint="Mis. 0812-0000-0000; tautan WhatsApp dibuat otomatis." optional>
                        <x-input name="office_whatsapp" type="tel" inputmode="tel" :value="$settings['office_whatsapp']" maxlength="30" />
                    </x-field>
                    <x-field label="Email" for="office_email" optional>
                        <x-input name="office_email" type="email" :value="$settings['office_email']" maxlength="255" />
                    </x-field>
                    <x-field label="Jam layanan" for="office_hours" hint="Mis. Senin sampai Jumat, pukul 08.00 sampai 15.00 WIT." optional>
                        <x-input name="office_hours" :value="$settings['office_hours']" maxlength="200" />
                    </x-field>
                </div>
                <p class="mt-4 text-caption">
                    <x-link :href="route('public.contact')" target="_blank" rel="noopener" class="font-normal">Lihat halaman Kontak</x-link>
                </p>
            </x-card>

            <x-form-actions sticky>
                <x-button variant="ghost" :href="route('admin.dashboard')">Batal</x-button>
                <x-button type="submit" processing="Menyimpan…">Simpan konten</x-button>
            </x-form-actions>
        </form>
    </div>
</x-layouts.app>
