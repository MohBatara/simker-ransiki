@use('App\Support\Format')

@php
    // Nilai di area aplikasi (.theme-app); halaman publik memakai nilai lama di @theme.
    $colors = [
        ['ink', '#1A1F1C', 'Teks utama', 'bg-ink'],
        ['ink-2', '#5F6B64', 'Teks sekunder', 'bg-ink-2'],
        ['ink-3', '#636D67', 'Keterangan (spesifikasi #8A948E digelapkan agar 4,5:1)', 'bg-ink-3'],
        ['line', '#E6E8E3', 'Border kartu dan tabel', 'bg-line'],
        ['line-soft', '#F0F2EE', 'Pemisah baris tabel', 'bg-line-soft'],
        ['line-input', '#858B87', 'Border kontrol form (3:1)', 'bg-line-input'],
        ['panel', '#FFFFFF', 'Panel konten dan kartu', 'bg-panel'],
        ['canvas', '#EEF0EC', 'Latar halaman dan sidebar', 'bg-canvas'],
        ['brand', '#1F5A47', 'Aksen utama', 'bg-brand'],
        ['brand-600', '#2F7A5F', 'Aksen terang', 'bg-brand-600'],
        ['brand-100', '#D3E6DB', 'Bingkai lembut aksen', 'bg-brand-100'],
        ['brand-50', '#EAF3EE', 'Latar lembut aksen', 'bg-brand-50'],
    ];
    $statuses = [
        ['sukses', '#1E6B3A', '#E3F4E8', 'Terverifikasi, aktif', 'bg-ok', 'bg-ok-bg'],
        ['menunggu', '#8A5A00', '#FBF0D4', 'Menunggu verifikasi', 'bg-wait', 'bg-wait-bg'],
        ['gagal', '#A63A2B', '#FBE4E1', 'Perlu revisi, terlambat, ditutup', 'bg-fix', 'bg-fix-bg'],
        ['info', '#2F5675', '#E6EEF5', 'Tindak lanjut berjalan', 'bg-info', 'bg-info-bg'],
        ['netral', '#4B544F', '#F1F2EF', 'Draf, tidak aktif', 'bg-idle', 'bg-idle-bg'],
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
        <x-admin.card title="Warna" description="Satu warna aksen. Warna status hanya untuk badge dan angka status.">
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
        </x-admin.card>

        <x-admin.card title="Tipografi" description="Public Sans, ketebalan 400 dan 600 saja.">
            <div class="space-y-4">
                <div>
                    <p class="text-caption text-ink-muted">text-display · 32px (text-display-sm 28px di HP)</p>
                    <p class="text-display-sm font-semibold md:text-display">Direktori kelompok ekonomi</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-admin-title · 26px, judul halaman area aplikasi</p>
                    <p class="text-admin-title font-semibold">Laporan bulanan</p>
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
                    <p class="text-caption text-ink-soft">Terakhir diperbarui {{ \App\Support\Format::orEmpty(Format::date('2026-10-09')) }}</p>
                </div>
                <div>
                    <p class="text-caption text-ink-muted">text-micro · 11px, hanya label sidebar tablet</p>
                    <p class="text-micro">Dasbor · Profil · Produk · Izin · Laporan · Notifikasi · Akun</p>
                </div>
                <div class="border-t border-line pt-4">
                    <p class="text-caption text-ink-muted">Format angka dan tanggal (App\Support\Format)</p>
                    <p class="tabular-nums">{{ \App\Support\Format::orEmpty(Format::number(1250000)) }} · {{ \App\Support\Format::orEmpty(Format::rupiah(1250000)) }} · {{ \App\Support\Format::orEmpty(Format::date('2026-10-09')) }} · {{ \App\Support\Format::orEmpty(Format::month('2026-09-01')) }} · kosong: {{ \App\Support\Format::orEmpty(Format::rupiah(null)) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Tombol">
            <div class="flex flex-wrap items-center gap-3">
                <x-button>Ajukan laporan</x-button>
                <x-button variant="secondary">Simpan draf</x-button>
                <x-button variant="ghost">Batal</x-button>
                <x-button variant="danger">Hapus</x-button>
                <x-button icon="plus">Tambah produk</x-button>
                <x-button disabled>Tidak aktif</x-button>
            </div>
        </x-admin.card>

        <form novalidate class="space-y-6" x-on:submit.prevent>
            <x-admin.form-section title="Form" description="Kartu section: judul dan petunjuk di kiri, isian di kanan. Label di atas, tanda wajib, bantuan dan error di bawah isian.">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-field label="Nama kelompok" for="nama" required>
                        <x-input name="nama" placeholder="Contoh: Kelompok Tani Contoh" required />
                    </x-field>
                    <x-field label="Nama kelompok (contoh error)" for="contoh_error">
                        <x-input name="contoh_error" />
                    </x-field>
                    <x-field label="Jenis kelembagaan" for="jenis" required>
                        <x-select name="jenis" placeholder="Pilih jenis" :options="['bumdes' => 'BUMDes', 'kub' => 'KUB', 'umkm' => 'UMKM', 'lainnya' => 'Lainnya']" required />
                    </x-field>
                    <x-field label="Jumlah anggota" for="anggota" hint="Isi 0 bila memang tidak ada. Kosongkan bila belum diketahui." optional>
                        <x-input name="anggota" type="number" min="0" inputmode="numeric" />
                    </x-field>
                    <x-field label="Ringkasan kegiatan" for="ringkasan" class="md:col-span-2">
                        <x-textarea name="ringkasan" placeholder="Tuliskan kegiatan utama bulan ini." />
                    </x-field>
                </div>
                <x-checkbox-group label="Bidang usaha" name="bidang[]">
                    <x-checkbox name="bidang[]" value="1" checked>Pertanian</x-checkbox>
                    <x-checkbox name="bidang[]" value="2">Perkebunan</x-checkbox>
                    <x-checkbox name="bidang[]" value="3">Perikanan</x-checkbox>
                    <x-checkbox name="bidang[]" value="4">Pengolahan Hasil</x-checkbox>
                    <x-checkbox name="bidang[]" value="5">UMKM</x-checkbox>
                </x-checkbox-group>
                <x-checkbox name="tanpa_aktivitas" hint="Bagian lain menjadi opsional.">Tidak ada aktivitas bulan ini</x-checkbox>
            </x-admin.form-section>

            <x-admin.form-section title="Unggah file" description="Kotak tarik-dan-lepas: form biasa (dropzone) dan unggahan langsung (file-upload).">
                <x-field label="Foto" for="foto" optional>
                    <x-admin.dropzone name="foto" />
                </x-field>
                <x-field label="Lampiran" for="lampiran" optional>
                    <x-file-upload url="" hint="Contoh tampilan. Unggahan di halaman ini tidak disimpan." />
                </x-field>
            </x-admin.form-section>

            <x-admin.form-bar cancel="#" submit="Simpan" />
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stat icon="user-group" label="Kelompok aktif" value="22" hint="dari 25 kelompok terdaftar" />
            <x-admin.stat icon="check-badge" label="Terverifikasi" :value="Format::number(14)" tone="success" hint="September 2026" href="#" />
            <x-admin.stat compact icon="clock" label="Menunggu verifikasi" value="5" tone="warning" hint="laporan" />
            <x-admin.stat compact icon="banknotes" label="Pendapatan" :value="Format::rupiah(48750000)" hint="diisi 18 dari 25 kelompok" href="#" />
        </div>

        <x-admin.card title="Grafik ringan" description="x-stacked-bar untuk status (angka legenda bisa diklik) dan x-bar-list untuk peringkat.">
            <div class="grid gap-6 md:grid-cols-2">
                <x-stacked-bar label="Pelaporan September 2026" :segments="[
                    ['label' => 'Terverifikasi', 'value' => 9, 'tone' => 'ok', 'href' => '#'],
                    ['label' => 'Menunggu verifikasi', 'value' => 4, 'tone' => 'wait', 'href' => '#'],
                    ['label' => 'Perlu revisi', 'value' => 2, 'tone' => 'fix', 'href' => '#'],
                    ['label' => 'Draf', 'value' => 1, 'tone' => 'idle', 'href' => '#'],
                    ['label' => 'Belum melapor', 'value' => 3, 'tone' => 'empty', 'href' => '#'],
                ]" />
                <x-bar-list :items="[
                    ['label' => 'Modal', 'value' => 6, 'href' => '#'],
                    ['label' => 'Pemasaran', 'value' => 4, 'href' => '#'],
                    ['label' => 'Bahan baku', 'value' => 1, 'href' => '#'],
                ]" />
            </div>
        </x-admin.card>

        <x-admin.card title="Badge status">
            <div class="flex flex-wrap gap-2">
                @foreach (App\Enums\ReportStatus::cases() as $status)
                    <x-admin.badge-status :status="$status" />
                @endforeach
                <x-admin.badge-status tone="danger" label="Terlambat" />
            </div>
        </x-admin.card>

        <div class="space-y-3">
            <h2 class="text-body font-semibold">Tabel</h2>
            <p class="text-ui text-ink-soft">Seluruh baris bisa diklik. Di HP baris berubah menjadi kartu. Kolom "Diajukan" disembunyikan di tablet.</p>
            <x-admin.table clickable>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    <x-admin.table.heading>Periode</x-admin.table.heading>
                    <x-admin.table.heading>Status</x-admin.table.heading>
                    <x-admin.table.heading numeric>Pendapatan</x-admin.table.heading>
                    <x-admin.table.heading optional>Diajukan</x-admin.table.heading>
                </x-slot:head>

                @foreach ($reports as $report)
                    <x-admin.table.row :href="route('dev.components.form')">
                        <x-admin.table.cell primary>{{ $report['organization'] }}</x-admin.table.cell>
                        <x-admin.table.cell subtitle>{{ \App\Support\Format::orEmpty(Format::month($report['period'])) }}</x-admin.table.cell>
                        <x-admin.table.cell badge><x-admin.badge-status :tone="$report['status'][1]" :label="$report['status'][0]" /></x-admin.table.cell>
                        <x-admin.table.cell label="Pendapatan" numeric>{{ \App\Support\Format::orEmpty(Format::rupiah($report['revenue'])) }}</x-admin.table.cell>
                        <x-admin.table.cell label="Diajukan" optional><x-admin.date :value="$report['submitted_at']" /></x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach

                <x-slot:footer>
                    <p class="text-caption text-ink-muted">Menampilkan 4 laporan</p>
                </x-slot:footer>
            </x-admin.table>
        </div>

        <x-admin.empty icon="document-text" title="Belum ada laporan" description="Buat laporan pertama untuk bulan September 2026.">
            <x-button icon="plus">Buat laporan</x-button>
        </x-admin.empty>

        <x-admin.card title="Alert">
            <div class="space-y-3">
                <x-alert>Batas pengiriman laporan September 2026 adalah 10 Oktober 2026.</x-alert>
                <x-alert tone="ok" dismissible>Laporan diajukan.</x-alert>
                <x-alert tone="wait" title="Menunggu verifikasi">Laporan terkunci sampai OPD meninjaunya.</x-alert>
                <x-alert tone="fix" title="Perlu revisi">Lengkapi angka penjualan kakao, lalu ajukan kembali.</x-alert>
            </div>
        </x-admin.card>

        <x-admin.card title="Tab, dropdown, dan modal">
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
        </x-admin.card>

        <x-admin.tabs
            :tabs="['menunggu' => ['label' => 'Perlu diverifikasi', 'href' => '#', 'count' => 4], 'semua' => ['label' => 'Semua laporan', 'href' => '#']]"
            active="menunggu"
            label="Contoh tab bertautan"
        />
    </div>

    <x-modal name="minta-revisi" title="Minta revisi">
        <p>Jelaskan bagian yang perlu diperbaiki. Catatan ini dikirim ke kelompok.</p>
        <x-field label="Catatan" for="catatan" class="mt-4">
            <x-textarea name="catatan" rows="3" />
        </x-field>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'minta-revisi')">Batal</x-button>
            <x-button>Minta revisi</x-button>
        </x-slot:footer>
    </x-modal>
</x-layouts.app>
