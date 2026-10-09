@use('App\Support\Format')

@php
    $colors = [
        ['ink', '#1C1F1E', 'Teks utama', 'bg-ink'],
        ['ink-soft', '#4A504D', 'Teks sekunder', 'bg-ink-soft'],
        ['ink-muted', '#686E6B', 'Keterangan, placeholder', 'bg-ink-muted'],
        ['line', '#E3E5E1', 'Border default', 'bg-line'],
        ['line-strong', '#C9CCC6', 'Border tegas (kartu, pemisah)', 'bg-line-strong'],
        ['line-input', '#858B87', 'Border kontrol form', 'bg-line-input'],
        ['paper', '#FFFFFF', 'Permukaan kartu', 'bg-paper'],
        ['canvas', '#F6F6F3', 'Latar halaman', 'bg-canvas'],
        ['subtle', '#EFF0EC', 'Latar baris/hover', 'bg-subtle'],
        ['brand', '#1F5A47', 'Aksen utama', 'bg-brand'],
        ['brand-hover', '#174536', 'Aksen saat hover', 'bg-brand-hover'],
        ['brand-tint', '#E7F0EC', 'Latar lembut aksen', 'bg-brand-tint'],
    ];
    $statuses = [
        ['ok', '#2F6B3A', '#E8F1E8', 'Terverifikasi', 'bg-ok', 'bg-ok-bg'],
        ['wait', '#8A5A00', '#FBF1DC', 'Diajukan / menunggu', 'bg-wait', 'bg-wait-bg'],
        ['fix', '#A3392B', '#F9E7E4', 'Perlu revisi / terlambat', 'bg-fix', 'bg-fix-bg'],
        ['idle', '#5C625F', '#EEEFEC', 'Draf / tidak aktif', 'bg-idle', 'bg-idle-bg'],
    ];
@endphp

<x-layouts.app
    title="Komponen"
    description="Tinjauan token desain dan komponen dasar. Hanya tersedia di environment local."
    :navigation="$navigation"
    notifications-url="#"
    user-name="Pengguna Contoh"
    user-role="Kelompok"
>
    <x-slot:actions>
        <x-button variant="secondary" :href="route('dev.components.form')">Mode form</x-button>
        <x-button variant="secondary" :href="route('dev.components.public')">Layout publik</x-button>
        <x-button :href="route('dev.components.guest')">Layout masuk</x-button>
    </x-slot:actions>

    <div class="space-y-6">
        <x-card title="Warna" description="Satu warna aksen. Warna status hanya untuk badge dan angka status.">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($colors as [$token, $hex, $use, $class])
                    <div class="flex items-center gap-3">
                        <span class="{{ $class }} size-10 shrink-0 rounded-control border border-line"></span>
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $token }} <span class="font-normal text-ink-muted">{{ $hex }}</span></p>
                            <p class="text-caption text-ink-soft">{{ $use }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 grid gap-3 border-t border-line pt-5 sm:grid-cols-2">
                @foreach ($statuses as [$token, $hex, $bgHex, $use, $class, $bgClass])
                    <div class="flex items-center gap-3">
                        <span class="flex shrink-0">
                            <span class="{{ $class }} size-10 rounded-l-control"></span>
                            <span class="{{ $bgClass }} size-10 rounded-r-control border border-l-0 border-line"></span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $token }} <span class="font-normal text-ink-muted">{{ $hex }} · {{ $bgHex }}</span></p>
                            <p class="text-caption text-ink-soft">{{ $use }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Tipografi" description="Public Sans, ketebalan 400 dan 600 saja.">
            <div class="space-y-4">
                <div>
                    <p class="text-caption text-ink-muted">text-display · 32px (text-display-sm 28px di HP)</p>
                    <p class="text-display-sm font-semibold md:text-display">Direktori kelompok ekonomi</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-page-title · 22px</p>
                    <p class="text-page-title font-semibold">Laporan bulanan</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-card-title · 18px</p>
                    <p class="text-card-title font-semibold">Ringkasan September 2026</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-body · 16px, paragraf publik</p>
                    <p class="max-w-prose text-body">Kelompok mengirim laporan setiap bulan. OPD meninjau dan memverifikasi laporan sebelum angkanya masuk ke statistik distrik.</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-ui · 14px, teks antarmuka dan tabel</p>
                    <p>Batas pengiriman tanggal 10 bulan berikutnya.</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-caption · 13px, keterangan</p>
                    <p class="text-caption text-ink-soft">Terakhir diperbarui {{ Format::date('2026-10-09') }}</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-micro · 11px, hanya label sidebar tablet</p>
                    <p class="text-micro">Dasbor · Profil · Produk · Izin · Laporan · Notifikasi · Akun</p>
                </div>
                <div class="border-t border-line pt-4">
                    <p class="text-caption text-ink-muted">Format angka dan tanggal (App\Support\Format)</p>
                    <p class="tabular-nums">{{ Format::number(1250000) }} · {{ Format::rupiah(1250000) }} · {{ Format::date('2026-10-09') }} · {{ Format::month('2026-09-01') }} · kosong: {{ Format::rupiah(null) }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="Tombol">
            <div class="flex flex-wrap items-center gap-3">
                <x-button>Ajukan laporan</x-button>
                <x-button variant="secondary">Simpan draf</x-button>
                <x-button variant="ghost">Batal</x-button>
                <x-button variant="danger">Hapus</x-button>
                <x-button icon="plus">Tambah produk</x-button>
                <x-button disabled>Tidak aktif</x-button>
            </div>
        </x-card>

        <x-card title="Form" description="Field menggabungkan label, input, pesan error, dan keterangan.">
            <form class="grid gap-5 md:grid-cols-2" x-on:submit.prevent>
                <x-field label="Nama kelompok" for="nama">
                    <x-input name="nama" placeholder="Contoh: Kelompok Tani Contoh" />
                </x-field>
                <x-field label="Nama kelompok (contoh error)" for="contoh_error">
                    <x-input name="contoh_error" />
                </x-field>
                <x-field label="Jenis kelembagaan" for="jenis">
                    <x-select name="jenis" placeholder="Pilih jenis" :options="['bumdes' => 'BUMDes', 'kub' => 'KUB', 'umkm' => 'UMKM', 'lainnya' => 'Lainnya']" />
                </x-field>
                <x-field label="Jumlah anggota" for="anggota" hint="Isi 0 bila memang tidak ada. Kosongkan bila belum diketahui." optional>
                    <x-input name="anggota" type="number" min="0" inputmode="numeric" />
                </x-field>
                <x-field label="Ringkasan kegiatan" for="ringkasan" class="md:col-span-2">
                    <x-textarea name="ringkasan" placeholder="Tuliskan kegiatan utama bulan ini." />
                </x-field>
                <x-checkbox-group label="Bidang usaha" name="bidang[]" class="md:col-span-2">
                    <x-checkbox name="bidang[]" value="1" checked>Pertanian</x-checkbox>
                    <x-checkbox name="bidang[]" value="2">Perkebunan</x-checkbox>
                    <x-checkbox name="bidang[]" value="3">Perikanan</x-checkbox>
                    <x-checkbox name="bidang[]" value="4">Pengolahan Hasil</x-checkbox>
                    <x-checkbox name="bidang[]" value="5">UMKM</x-checkbox>
                </x-checkbox-group>
                <div class="md:col-span-2">
                    <x-checkbox name="tanpa_aktivitas" hint="Bagian lain menjadi opsional.">Tidak ada aktivitas bulan ini</x-checkbox>
                </div>
                <x-field label="Lampiran" for="lampiran" class="md:col-span-2" optional>
                    <x-file-upload name="lampiran" multiple />
                </x-field>
                <x-form-actions sticky class="md:col-span-2">
                    <x-button variant="secondary" type="submit">Simpan draf</x-button>
                    <x-button type="submit">Ajukan laporan</x-button>
                </x-form-actions>
            </form>
        </x-card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Kelompok aktif" value="22" hint="dari 25 kelompok terdaftar" />
            <x-stat label="Terverifikasi" :value="Format::number(14)" tone="ok" hint="September 2026" />
            <x-stat label="Menunggu verifikasi" value="5" tone="wait" />
            <x-stat label="Pendapatan terverifikasi" :value="Format::rupiah(48750000)" hint="dari 18 dari 25 kelompok yang mengisi" />
        </div>

        <x-card title="Badge status">
            <div class="flex flex-wrap gap-2">
                <x-badge tone="idle">Draf</x-badge>
                <x-badge tone="wait">Diajukan</x-badge>
                <x-badge tone="fix">Perlu revisi</x-badge>
                <x-badge tone="ok">Terverifikasi</x-badge>
            </div>
        </x-card>

        <div class="space-y-3">
            <h2 class="text-card-title font-semibold">Tabel</h2>
            <p class="text-ui text-ink-soft">Seluruh baris bisa diklik. Di HP baris berubah menjadi kartu. Kolom "Diajukan" disembunyikan di tablet.</p>
            <x-table clickable>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading>Periode</x-table.heading>
                    <x-table.heading>Status</x-table.heading>
                    <x-table.heading numeric>Pendapatan</x-table.heading>
                    <x-table.heading optional>Diajukan</x-table.heading>
                </x-slot:head>

                @foreach ($reports as $report)
                    <x-table.row :href="route('dev.components.form')">
                        <x-table.cell primary>{{ $report['organization'] }}</x-table.cell>
                        <x-table.cell subtitle>{{ Format::month($report['period']) }}</x-table.cell>
                        <x-table.cell badge><x-badge :tone="$report['status'][1]">{{ $report['status'][0] }}</x-badge></x-table.cell>
                        <x-table.cell label="Pendapatan" numeric>{{ Format::rupiah($report['revenue']) }}</x-table.cell>
                        <x-table.cell label="Diajukan" optional>{{ Format::date($report['submitted_at']) }}</x-table.cell>
                    </x-table.row>
                @endforeach

                <x-slot:footer>
                    <p class="text-caption text-ink-muted">Menampilkan 4 laporan</p>
                </x-slot:footer>
            </x-table>
        </div>

        <x-empty-state title="Belum ada laporan" description="Buat laporan pertama untuk bulan September 2026.">
            <x-button icon="plus">Buat laporan</x-button>
        </x-empty-state>

        <x-card title="Alert">
            <div class="space-y-3">
                <x-alert>Batas pengiriman laporan September 2026 adalah 10 Oktober 2026.</x-alert>
                <x-alert tone="ok" dismissible>Laporan diajukan.</x-alert>
                <x-alert tone="wait" title="Menunggu verifikasi">Laporan terkunci sampai OPD meninjaunya.</x-alert>
                <x-alert tone="fix" title="Perlu revisi">Lengkapi angka penjualan kakao, lalu ajukan kembali.</x-alert>
            </div>
        </x-card>

        <x-card title="Tab, dropdown, dan modal">
            <x-tabs :tabs="['ringkasan' => 'Ringkasan', 'produk' => 'Produk', 'riwayat' => 'Riwayat']">
                <x-tabs.panel name="ringkasan">Isi tab ringkasan.</x-tabs.panel>
                <x-tabs.panel name="produk">Kakao, kopi, dan pinang.</x-tabs.panel>
                <x-tabs.panel name="riwayat">Belum ada riwayat.</x-tabs.panel>
            </x-tabs>

            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                <x-dropdown align="left">
                    <x-slot:trigger>
                        <x-button variant="secondary">
                            Pilihan
                            <x-heroicon-o-chevron-down class="-mr-1 size-4" />
                        </x-button>
                    </x-slot:trigger>
                    <x-dropdown.item href="#" icon="pencil-square">Ubah</x-dropdown.item>
                    <x-dropdown.item href="#" icon="arrow-down-tray">Unduh</x-dropdown.item>
                    <x-dropdown.item icon="trash">Hapus</x-dropdown.item>
                </x-dropdown>

                <x-button variant="secondary" x-on:click="$dispatch('open-modal', 'minta-revisi')">Buka modal</x-button>
            </div>
        </x-card>

        <x-tabs
            :tabs="['menunggu' => 'Menunggu', 'semua' => 'Semua laporan']"
            active="menunggu"
            :links="['menunggu' => '#', 'semua' => '#']"
        />
    </div>

    <x-modal name="minta-revisi" title="Minta revisi">
        <p>Jelaskan bagian yang perlu diperbaiki. Catatan ini dikirim ke kelompok.</p>
        <x-field label="Catatan" for="catatan" class="mt-4">
            <x-textarea name="catatan" rows="3" />
        </x-field>
        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'minta-revisi')">Batal</x-button>
            <x-button>Minta revisi</x-button>
        </x-slot:footer>
    </x-modal>
</x-layouts.app>
