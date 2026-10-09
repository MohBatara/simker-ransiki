<x-layouts.app title="Konten Website" description="Teks dan foto yang tampil di website publik." :back="route('admin.dashboard')" focus>
    <div class="space-y-5">
        <x-card title="Foto sampul" description="Opsional. Tampil lebar di bawah pembuka beranda. Gunakan foto lanskap asli Ransiki, bukan infografis atau gambar berisi teks.">
            <div class="space-y-4">
                @if ($settings['cover_photo_path'])
                    <img src="{{ route('public.cover', ['v' => substr(md5($settings['cover_photo_path']), 0, 8)]) }}" alt="Foto sampul saat ini" class="aspect-[4/3] w-full rounded-card object-cover md:aspect-[21/9]">
                    <form method="POST" action="{{ route('admin.content.cover.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" type="submit">Hapus foto sampul</x-button>
                    </form>
                @endif
                <x-file-upload :url="route('admin.content.cover.store')" kind="photo" :multiple="false" :remaining="1" hint="Foto lanskap JPG atau PNG, maksimal 10 MB. Foto diperkecil dan data lokasinya dibuang otomatis. Foto lama akan diganti." />
            </div>
        </x-card>

        <form method="POST" action="{{ route('admin.content.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

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

            <x-card title="Beranda">
                <x-field label="Pengantar beranda" for="home_intro" hint="Satu atau dua kalimat di bawah judul beranda. Maksimal 300 karakter." optional>
                    <x-textarea name="home_intro" rows="3" maxlength="300" :value="$settings['home_intro']" />
                </x-field>
            </x-card>

            <x-card title="Profil Ransiki" description="Bisa memakai format sederhana: ## Judul, - daftar, **tebal**, [teks](tautan). Pisahkan paragraf dengan satu baris kosong.">
                <div class="space-y-5">
                    <x-field label="Profil distrik" for="profile_body" optional>
                        <x-textarea name="profile_body" rows="12" :value="$settings['profile_body']" />
                    </x-field>
                    <x-field label="Potensi ekonomi" for="economic_potential" optional>
                        <x-textarea name="economic_potential" rows="8" :value="$settings['economic_potential']" />
                    </x-field>
                </div>
                <p class="mt-4 text-caption">
                    <a href="{{ route('public.profile') }}" target="_blank" rel="noopener" class="rounded-control text-brand hover:text-brand-hover">Lihat halaman Profil Ransiki</a>
                </p>
            </x-card>

            <x-card title="Kontak kantor distrik" description="Tampil di halaman Kontak dan di bagian bawah semua halaman publik.">
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
                    <x-field label="Jam layanan" for="office_hours" hint="Mis. Senin–Jumat, 08.00–15.00 WIT." optional>
                        <x-input name="office_hours" :value="$settings['office_hours']" maxlength="200" />
                    </x-field>
                </div>
                <p class="mt-4 text-caption">
                    <a href="{{ route('public.contact') }}" target="_blank" rel="noopener" class="rounded-control text-brand hover:text-brand-hover">Lihat halaman Kontak</a>
                </p>
            </x-card>

            <x-form-actions sticky>
                <x-button variant="ghost" :href="route('admin.dashboard')">Batal</x-button>
                <x-button type="submit">Simpan konten</x-button>
            </x-form-actions>
        </form>
    </div>
</x-layouts.app>
