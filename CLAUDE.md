# CLAUDE.md — SIM-KER Distrik Ransiki

Sistem Informasi dan Monitoring Kelembagaan Ekonomi Distrik Ransiki (SIM-KER).
Platform web untuk mendata kelompok/kelembagaan ekonomi, menampilkan profilnya ke publik,
menerima laporan bulanan dari kelompok, diverifikasi oleh OPD, dan dipantau lewat dasbor.

Baca seluruh file ini sebelum mulai bekerja. Jika ada instruksi pengguna yang bertentangan
dengan file ini, ikuti pengguna lalu sarankan pembaruan file ini.

---

## 1. Cara bekerja

- Pengguna adalah pengembang yang baru memakai macOS (MacBook Pro M1) dan Docker. Jelaskan perintah
  yang dijalankan dengan singkat dalam Bahasa Indonesia.
- Kerjakan per langkah kecil. Setelah setiap fitur: jalankan migrasi/tes, pastikan halaman terbuka
  tanpa error, lalu beri ringkasan singkat apa yang berubah dan cara mencobanya di browser.
- Jangan menambah paket Composer/npm baru tanpa menyebutkan alasannya terlebih dahulu.
- Jangan mengubah token desain (Bagian 7) tanpa persetujuan pengguna.
- Semua teks yang tampil ke pengguna dalam Bahasa Indonesia. Nama kode (class, method, variabel,
  tabel, kolom) dalam Bahasa Inggris.
- Komentar kode secukupnya, hanya untuk logika yang tidak jelas. Jangan menulis komentar yang
  mengulang isi kode.
- Jangan mengarang data resmi (nama kampung asli, nama pejabat, nomor izin). Gunakan data contoh
  yang jelas bersifat contoh sampai data resmi diberikan.

---

## 2. Stack dan lingkungan

- Laravel 13, PHP 8.3+
- Blade + Tailwind CSS v4 (melalui Vite) + Alpine.js untuk interaksi kecil (dropdown, modal, tab,
  menu mobile). Tidak memakai Livewire, Filament, Inertia, React, atau Vue.
- MySQL 8 melalui Laravel Sail (Docker), berjalan di Apple Silicon (arm64).
- Ikon: satu set saja, Heroicons outline (paket `blade-ui-kit/blade-heroicons`).
- Font: Public Sans, dipasang lokal melalui npm `@fontsource-variable/public-sans` (tidak memakai
  Google Fonts CDN).
- Tes: Pest (atau PHPUnit bawaan bila Pest belum terpasang).
- Hosting akhir: Hostinger. Hindari fitur yang butuh proses latar permanen (queue worker,
  websocket). Queue memakai driver `sync`. Scheduler cukup lewat satu cron `schedule:run`.

### Perintah harian (alias `sail` = `./vendor/bin/sail`)

```bash
sail up -d                 # menyalakan container
sail stop                  # mematikan container
sail artisan migrate:fresh --seed
sail artisan test
sail npm run dev           # Vite untuk pengembangan
sail npm run build         # build aset produksi
sail artisan route:list
```

Aplikasi terbuka di http://localhost. Jika port 80 atau 3306 bentrok, ubah `APP_PORT` /
`FORWARD_DB_PORT` di `.env`.

---

## 3. Peran pengguna dan hak akses

Tiga peran login + pengunjung publik. Kolom `users.role` memakai enum `Role`.

| Peran | Kode | Cakupan data |
|---|---|---|
| Administrator Distrik | `admin` | Seluruh data distrik |
| OPD | `opd` | Hanya kelompok yang terhubung dengan OPD-nya (`organization_opd`) |
| Kelompok | `kelompok` | Hanya kelompoknya sendiri (`users.organization_id`) |
| Publik | (tanpa login) | Hanya kelompok aktif yang `is_public = true`, dan hanya kolom publik |

Empat OPD memakai tampilan yang sama; data otomatis tersaring berdasarkan `users.opd_id`.

### Hak per jenis data

| Data | Admin | OPD (hanya kelompok binaan) | Kelompok (hanya miliknya) |
|---|---|---|---|
| Profil kelompok (termasuk bidang usaha), produk, izin | lihat, buat, ubah, hapus | lihat, ubah (membantu melengkapi data) | lihat, ubah |
| Nama, jenis, kampung, OPD terkait, status, tampil di direktori publik, bulan mulai wajib lapor | ubah | – (tampil baca-saja) | – (tampil baca-saja) |
| Penanda "Produk unggulan" (`is_featured`) | ubah | – | – |
| Laporan bulanan | lihat, verifikasi, minta revisi | lihat, verifikasi, minta revisi (tidak membuat/mengubah isi) | lihat, buat, ubah & ajukan saat `draft`/`revision` |
| Lampiran | mengikuti hak atas induknya | mengikuti hak atas induknya | mengikuti hak atas induknya |
| Pengguna | kelola, reset kata sandi (tidak bisa menonaktifkan/menghapus diri sendiri) | – | – |

Laporan `verified` tidak bisa diubah siapa pun. Kelompok `closed` tidak bisa membuat laporan baru.

### Aturan akses (WAJIB)

- Setiap pembatasan akses dicek di server melalui Policy dan/atau query scope. Menyembunyikan
  menu atau tombol saja tidak cukup.
- Gunakan Policy untuk `Organization`, `MonthlyReport`, `Product`, `License`, `Attachment`, `User`.
- Gunakan scope `Organization::visibleTo(User $user)` untuk semua daftar kelompok. Policy memakai
  `Organization::isVisibleTo()` yang hasilnya selalu sama dengan scope (ada tesnya).
- Kolom khusus admin di form bersama diabaikan (rule `exclude`) bila dikirim pengguna lain, bukan ditolak.
- Membuka area peran lain (mis. kelompok membuka `/admin`) → 403.
- Membuka data milik orang lain lewat ID di URL (mis. `/kelompok/laporan/15`) → 404
  (`Response::denyAsNotFound()`), agar keberadaan ID tidak terbongkar. Data yang terlihat tetapi
  aksinya tidak diizinkan (mis. mengubah laporan `submitted`) → 403 dengan pesan.
- File privat disimpan di disk `local` (bukan `public`) dan hanya diunduh melalui route yang
  memeriksa Policy. Ini berlaku juga untuk foto produk.
- Route file dipakai bersama semua peran (di luar prefix peran), dijaga `auth` + Policy:
  `/lampiran/{id}` (tampil inline), `/lampiran/{id}/unduh`, `POST /lampiran/{kelompok|izin}/{id}`,
  `DELETE /lampiran/{id}`, `POST/DELETE /foto-produk/{id}`. Hak unggah/hapus = hak `update` atas
  induknya; siapa pun yang berhak boleh menghapus file unggahan orang lain.
- `GET /foto-produk/{id}` boleh tanpa login bila produk `is_public` dan kelompoknya aktif serta
  `is_public`; selain itu wajib login + Policy, tamu tanpa hak mendapat 404.
- Header file: `X-Content-Type-Options: nosniff`; PDF tampil inline dengan
  `Content-Security-Policy: sandbox`, plus tombol "Unduh" (`Content-Disposition: attachment`).
- Setiap fitur baru yang menyentuh data wajib punya feature test akses: pemilik bisa, peran lain
  yang tidak berhak ditolak.

### Route

```
/                       publik
/direktori, /direktori/{slug}, /produk, /profil, /kontak
/login (GET, POST), /logout (POST saja)
/akun/kata-sandi        auth + active (semua peran; juga halaman ganti kata sandi wajib)
/admin/...              auth + active + password.changed + role:admin
/opd/...                auth + active + password.changed + role:opd
/kelompok/...           auth + active + password.changed + role:kelompok
```

Setelah login, arahkan pengguna ke dasbor sesuai perannya. Halaman tujuan sebelum login hanya
dipakai bila berada di area perannya sendiri.

### Website publik (data)
- Satu pintu data publik: scope `Organization::publiclyListed()` (status `active` + `is_public`) dan
  `Product::publiclyListed()` (produk `is_public` milik kelompok yang terdaftar publik). Dipakai semua
  halaman publik, angka beranda, sitemap, dan Open Graph. Kelompok lain → 404, juga bila dulu publik.
- Query publik memilih kolom publik saja (`select`): nama, jenis, kampung, bidang usaha, deskripsi,
  tahun berdiri, kapasitas produksi, pasar. Tidak pernah tampil: jumlah anggota, tenaga kerja, aset,
  fasilitas, peralatan, keuangan, laporan, masalah, lampiran, akun, nomor izin.
- Kontak (ketua, telepon, email, alamat) baru diambil dari database bila `show_contact_publicly`.
- Bagian Legalitas hanya tampil bila status hukum `registered` (ditulis "terdaftar") atau ada izin
  `issued` (jenisnya saja). Status lain tidak pernah tampil di publik.
- Angka beranda dihitung unik dan di-cache 10 menit (`App\Support\PublicDirectory`). Cache hanya
  boleh berisi array/teks: Laravel menolak membuka objek dari cache (`cache.serializable_classes`).

### Login dan session
- Login dengan username ATAU email + kata sandi. Username disimpan dan dicocokkan dalam huruf
  kecil (trim + lowercase), termasuk di seeder dan saat Admin membuat akun.
- Tidak ada daftar akun sendiri dan tidak ada "lupa kata sandi" via email; reset oleh Admin.
- Pesan gagal sama untuk username tidak ada dan kata sandi salah. Rate limit: 5 percobaan per
  menit per (username/email + IP), 20 per menit per IP.
- Hanya setelah kata sandi benar: akun nonaktif ditolak ("Akun ini dinonaktifkan. Hubungi Admin
  distrik."), akun OPD tanpa `opd_id` atau kelompok tanpa `organization_id` ditolak ("Akun belum
  terhubung ke OPD/kelompok. Hubungi Admin distrik.").
- Middleware `active` mengeluarkan pengguna yang dinonaktifkan atau tidak terhubung saat sedang login.
- Login meregenerasi session dan mencatat `last_login_at`. Logout hanya lewat POST.
- `must_change_password` (default true; diset true saat Admin membuat akun atau mereset kata
  sandi): middleware `password.changed` memaksa ganti kata sandi di `/akun/kata-sandi`.
  Kata sandi minimal 8 karakter, berbeda dari yang lama, dan wajib mengisi kata sandi saat ini.
- Session: `SESSION_LIFETIME=240` menit, `SESSION_EXPIRE_ON_CLOSE=true`, tanpa "Ingat saya".

---

## 4. Model data

Nama tabel Bahasa Inggris, snake_case, jamak. Jangan memakai nama tabel `groups`
(kata kunci MySQL 8); kelompok ekonomi disebut `organizations` di kode, "Kelompok" di tampilan.

Konvensi kolom:
- Kolom status/enum disimpan sebagai `varchar` dan di-cast ke enum PHP (`app/Enums`), bukan tipe
  `ENUM` MySQL, agar menambah nilai tidak perlu mengubah struktur tabel.
- Uang disimpan sebagai bilangan bulat Rupiah (`bigInteger`), tanpa desimal. Kolom yang bisa
  negatif (`profit`, `members_change`) signed.
- Jumlah produksi/penjualan `decimal(12,2)` karena satuan bisa kg/liter.
- Data master tidak bisa dihapus selama masih dipakai (FK restrict). Kelompok tidak bisa dihapus
  bila punya laporan atau akun; kelompok yang berhenti diberi status `closed`.

### Data master
- `opds`: id, name, short_name, description
- `villages` (kampung): id, name
- `institution_types` (jenis kelembagaan): id, name, code
  Contoh: BUMDes, KUB, KDMP, KNMP, Kelompok Pertanian, Kelompok Perkebunan, Kelompok Perikanan,
  UMKM, Lainnya
- `business_sectors` (bidang usaha): id, name
  Contoh: Pertanian, Perkebunan, Perikanan, UMKM, Perdagangan, Pengolahan Hasil, Lainnya

Jangan mengarang kepanjangan akronim resmi (KDMP, KNMP, dsb.). Simpan apa adanya.

### Pengguna
- `users`: id, name, username (unik), email (nullable), phone (nullable), password,
  role, opd_id (nullable, wajib bila role=opd), organization_id (nullable, wajib bila role=kelompok),
  is_active, last_login_at, must_change_password (bool, default true)
- Login memakai username ATAU email. Banyak ketua kelompok tidak punya email.
- Akun dibuat oleh Admin. Admin dapat mengatur ulang kata sandi.
- Satu kelompok boleh punya lebih dari satu akun (beberapa user dengan organization_id sama).

### Kelompok
- `organizations`: id, slug, name, institution_type_id, village_id, address, founded_year,
  leader_name, phone, email, members_count, description,
  production_capacity, markets, workforce_count, assets, facilities, equipment,
  legal_status (enum LegalStatus: unknown, none, in_process, registered; default unknown),
  legal_number (teks, nullable), legal_notes (text, nullable),
  status (enum: active, inactive, closed; default active),
  reporting_starts_on (date, awal bulan; kelompok tidak dianggap terlambat sebelum tanggal ini),
  is_public (bool, default false), show_contact_publicly (bool, default false),
  created_by, updated_by, timestamps
- Peringatan nama mirip (`App\Support\SimilarNames`, kampung yang sama): buang awalan umum (kelompok,
  kelompok tani, kelompok nelayan, kub, bumdes, umkm, koperasi; "kel." = kelompok). "Tani" dan
  "nelayan" adalah penanda jenis: bila berbeda, tidak mirip. Angka di nama berbeda → tidak mirip.
  Mirip bila sisa nama sama, kemiripan ≥ 90%, atau Levenshtein ≤ 2 untuk sisa nama ≥ 8 huruf.
  Saat tambah: konfirmasi sekali ("tetap simpan"); saat ubah nama/kampung: disimpan lalu diberi peringatan.
- URL area login memakai ID (`/admin/kelompok/12`); slug hanya untuk halaman publik.
- `organization_sector` (pivot): organization_id, business_sector_id
  Primary key gabungan + index tunggal pada `business_sector_id`.
- `organization_opd` (pivot): organization_id, opd_id
  Primary key gabungan + index tunggal pada `opd_id` (dipakai filter data per OPD).
  Satu kelompok boleh punya banyak bidang usaha dan banyak OPD.
  Hubungan OPD ditetapkan manual oleh Admin, tidak otomatis dari bidang usaha.
- `licenses` (perizinan): id, organization_id, type, number, status
  (enum: none, in_process, issued, needs_fix), submitted_on, issued_on, notes
- `products`: id, organization_id, name, description, unit, capacity, is_featured, is_public,
  photo_path (nullable)
- `attachments` (polimorfik): id, attachable_type, attachable_id, disk, path, original_name,
  mime_type, size, visibility (enum: private, public; default private), uploaded_by
  Tipe yang diizinkan: JPG, PNG, PDF. Maksimal 10 MB per file, 10 file per entitas.
  Gambar dikompres/diubah ukurannya saat diunggah (koneksi di lapangan lambat).
  `attachable_type` memakai alias morph map (`organization`, `license`, `product`,
  `monthly_report`), bukan nama class.
  File fisik ikut dihapus saat baris lampiran atau induknya dihapus (model event, trait
  `HasAttachments`). Foto produk (`photo_path`) juga dihapus saat produk dihapus.

### Laporan bulanan
- `monthly_reports`: id, organization_id, period (date, selalu tanggal 1),
  status (enum: draft, submitted, revision, verified),
  no_activity (bool),
  activity_summary, activity_count, achievements,
  members_active, workforce_count, members_change,
  income_sales, income_other, costs, profit,
  submitted_at, submitted_by, verified_at, verified_by, timestamps
  UNIQUE (organization_id, period): satu laporan per kelompok per bulan.
  `profit` tidak diisi pengguna: dihitung `income_sales + income_other − costs` dan disimpan saat
  laporan disimpan (model event). NULL bila salah satu komponen NULL. Di form tampil baca-saja
  dengan keterangan "dihitung otomatis".
- `report_items` (produksi & penjualan per produk): id, monthly_report_id, product_id (nullable),
  product_name, production_qty, production_unit, sales_qty, sales_unit, revenue
- `report_issues` (masalah & kebutuhan): id, monthly_report_id, kind (enum: problem, support),
  category, description
  Kategori masalah: modal, bahan_baku, peralatan, sdm, pemasaran, transportasi, legalitas,
  produksi, lainnya
  Kategori kebutuhan: bantuan_alat, pelatihan, modal, pendampingan, pemasaran, legalitas,
  akses_pasar, lainnya
- `report_reviews` (riwayat): id, monthly_report_id, user_id,
  action (enum: submitted, revision_requested, verified, comment), note, created_at
  Tabel ini sekaligus menjadi riwayat audit laporan.

### Konten & notifikasi
- `site_settings`: key, value. Kunci di `SiteSetting::KEYS`: home_intro, profile_body,
  economic_potential (Markdown terbatas, dirender `Format::markdown` tanpa HTML mentah), office_address,
  office_phone, office_whatsapp, office_email, office_hours, cover_photo_path, cover_caption,
  cover_credit. Di-cache sampai disimpan ulang.
- `notifications`: tabel bawaan Laravel (database notifications)
- (diupayakan) `posts` untuk Berita & Kegiatan

### Aturan angka
- Kolom angka laporan nullable. `NULL` = tidak diketahui/tidak diisi. `0` = nol yang dikonfirmasi.
  Jangan pernah mengubah NULL menjadi 0.
- Saat menjumlahkan, tampilkan keterangan bila sebagian data kosong
  (mis. "dari 18 dari 25 kelompok yang mengisi").
- Jangan menjumlahkan produksi dengan satuan berbeda. Kelompokkan per produk + satuan.
- Statistik resmi di dasbor hanya dari laporan berstatus `verified`.
- Hitung kelompok secara unik (`COUNT(DISTINCT organizations.id)`); kelompok dengan banyak bidang
  usaha atau banyak OPD tidak boleh terhitung ganda.

---

## 5. Alur laporan bulanan

```
draft ──ajukan──▶ submitted ──verifikasi──▶ verified ──buka kembali (Admin)──▶ revision
                      │
                      └──minta revisi──▶ revision ──perbaiki & ajukan──▶ submitted
```

- Kelompok hanya bisa mengubah laporan berstatus `draft` atau `revision`.
- Laporan `submitted` terkunci untuk kelompok sampai OPD bertindak.
- Laporan `verified` tidak bisa diubah (tahap inti). Lampiran laporan berstatus `verified` tidak
  boleh dihapus siapa pun, termasuk Admin.
- OPD yang boleh memverifikasi: OPD mana pun yang terhubung dengan kelompok tersebut.
  Admin juga boleh. (Keputusan sementara, menunggu konfirmasi klien.)
- Permintaan revisi wajib disertai catatan (min 10 karakter); catatan verifikasi opsional.
- Verifikasi final bagi OPD. Hanya Admin yang bisa "Buka kembali untuk revisi" (verified → revision,
  aksi riwayat `reopened`, alasan wajib min 10 karakter). `verified_at`/`verified_by` dikosongkan;
  riwayat tetap menyimpan verifikasi lama. Alasannya tampil di form kelompok seperti catatan revisi.
- Logika tindakan peninjau ada di `App\Actions\Reports\ReviewReport` (transaksi + `lockForUpdate`):
  hak lihat (404), lalu status, lalu hak tindakan. Bila peninjau lain bertindak lebih dulu, yang kedua
  mendapat pesan "Laporan ini sudah ditinjau oleh …" tanpa baris riwayat ganda.
- Draf yang belum pernah diajukan (`submitted_at` kosong) tampil di daftar Admin/OPD sebagai
  "Draf, belum diajukan" tanpa tautan; isinya (termasuk lampiran) 404 bagi Admin/OPD.
- Antrean verifikasi OPD (`/opd/verifikasi`): laporan `submitted`, urut dari pengajuan TERAKHIR yang
  paling lama (dari `report_reviews`), dengan penanda "Diajukan ulang".
- "Masalah & Kebutuhan" (`/opd/masalah`, `/admin/masalah`): isian bagian E/F hanya dari laporan
  `submitted`/`verified` (bukan draf atau revisi), scope `ReportIssue::reviewableBy()`.
- Kelompok bisa memilih "Tidak ada aktivitas bulan ini"; bagian lain menjadi opsional.
- Wajib saat diajukan (bila ada aktivitas): ringkasan aktivitas dan anggota aktif (0 = terisi).
  Tenaga kerja, keuangan, dokumentasi opsional; keuangan kosong tetap NULL (laba NULL, ada peringatan).
  Baris yang diisi harus lengkap: nama produk, satuan bila jumlahnya diisi, kategori masalah/kebutuhan,
  uraian bila kategori "lainnya". Aturan ini ada di `App\Actions\Reports\SubmitReport` dan dicerminkan
  di `resources/js/report-form.js`. Draf boleh menyimpan baris belum lengkap
  (`report_items.product_name` dan `report_issues.category` nullable).
- Daftar laporan: satu baris per bulan dari `reporting_starts_on` sampai bulan berjalan. Laporan dibuat
  per bulan ("Buat laporan" langsung membuat draf). Bulan berjalan boleh dibuat sebagai draf, tetapi baru
  bisa diajukan mulai tanggal 1 bulan berikutnya (Policy `submit`).
- Kelompok `closed` tidak bisa membuat laporan baru, tetapi draf/revisi yang ada tetap boleh diselesaikan.
- Batas pengiriman: `config/simker.php` (`reports.deadline_day` = 10, `reports.timezone` = Asia/Jayapura),
  pukul 23.59 WIT. Hitung lewat `MonthlyReport::deadlineFor()`, jangan ditulis langsung di tempat lain.
- `submitted_at`/`submitted_by` menyimpan pengajuan PERTAMA dan tidak diubah saat diajukan ulang
  setelah revisi; setiap pengajuan tercatat di `report_reviews`.
- Terlambat (`isOverdue()`): lewat tenggat dan `submitted_at` kosong. Laporan yang diajukan tepat waktu
  lalu diminta revisi tidak terlambat. "Diajukan terlambat" (`wasSubmittedLate()`, informasi saja):
  `submitted_at` melewati tenggat periodenya.
- Status "belum melapor" dihitung untuk kelompok `active` dan `inactive` yang
  `reporting_starts_on` ≤ periode. Kelompok `closed` tidak dihitung.
  Laporan `submitted` yang belum ditinjau TIDAK dihitung terlambat.

### Form laporan (HP, koneksi lambat)
- Langkah bertahap A–H dalam satu halaman (Alpine `reportForm`): A Aktivitas, B Produksi & penjualan,
  C SDM, D Keuangan, E Permasalahan, F Kebutuhan dukungan, G Dokumentasi, H Periksa & ajukan.
- Simpan otomatis lewat `PATCH /kelompok/laporan/{id}/draf` (JSON) setiap
  `reports.autosave_seconds` (30), saat pindah bagian, dan saat tab disembunyikan. Versi = `updated_at`;
  versi beda → 409 (disimpan lebih dulu dari perangkat/akun lain). "Ajukan" menyimpan dulu, lalu
  `POST .../ajukan` memvalidasi data yang tersimpan.
- Cadangan `localStorage` dengan kunci `simker:laporan:{user_id}:{report_id}` (hanya ditawarkan ke akun
  yang sama), dihapus setelah tersimpan ke server dan saat keluar (form logout bertanda `data-logout`).
- "Salin dari bulan lalu" (bagian B dan C): dari laporan sebelumnya yang terdekat milik kelompok sama;
  hanya daftar produk + satuan, anggota aktif, dan tenaga kerja.
- Angka diketik sebagai teks, dibaca `App\Support\NumberInput` (PHP) dan `resources/js/numbers.js`
  (pratinjau "Terbaca: ..." di `x-number-input`); keduanya harus sama:
  * uang dan bilangan bulat: buang "Rp", spasi, semua titik dan koma ("12,5" → 125);
  * jumlah desimal: koma = desimal; titik = pemisah ribuan HANYA bila setiap kelompok tepat 3 digit,
    selain itu titik = desimal ("12.5" → 12,5; "1.250" → 1250; "1.250,5" → 1250,5); maks 2 desimal.

Setiap perubahan status mencatat baris di `report_reviews` dan mengirim notifikasi (hanya akun aktif):
- diajukan → semua user OPD terkait (`ReportSubmitted`)
- minta revisi / terverifikasi / dibuka kembali → semua user kelompok tersebut (`ReportReviewed`)

---

## 6. Menu per peran

**Publik:** Beranda · Profil Ransiki · Direktori Kelompok · Produk Unggulan · Kontak · Masuk

**Kelompok:** Dasbor · Profil Kelompok · Produk · Legalitas & Izin · Laporan Bulanan ·
Notifikasi · Akun Saya

**OPD:** Dasbor · Kelompok Binaan · Verifikasi Laporan · Semua Laporan ·
Masalah & Kebutuhan · Notifikasi · Akun Saya

**Admin:** Dasbor Distrik · Semua Kelompok · Semua Laporan · Masalah & Kebutuhan · Pengguna ·
Data Master (Kampung, Jenis Kelembagaan, Bidang Usaha, OPD) · Konten Website · Notifikasi

Pakai "Dasbor", bukan "Dashboard", di semua teks antarmuka dan semua ukuran layar.

### Label ringkas (sidebar tablet dan navigasi bawah HP)

| Menu lengkap | Label ringkas |
|---|---|
| Dasbor, Dasbor Distrik | Dasbor |
| Profil Kelompok | Profil |
| Produk | Produk |
| Legalitas & Izin | Izin |
| Laporan Bulanan, Semua Laporan | Laporan |
| Notifikasi | Notifikasi |
| Akun Saya | Akun |
| Kelompok Binaan, Semua Kelompok | Kelompok |
| Verifikasi Laporan | Verifikasi |
| Pengguna | Pengguna |
| Data Master | Master |
| Konten Website | Konten |
| Masalah & Kebutuhan | Masalah |

Nama menu lengkap tetap tampil di sidebar desktop, dan sebagai tooltip saat label ringkas
di-hover atau difokuskan.

---

## 7. Sistem desain

Tujuan: elegan, sederhana, terasa seperti aplikasi, dan TIDAK tampak seperti desain buatan AI.
Rujukan rasa: aplikasi administrasi yang tenang dan rapi, seperti dokumen pemerintah yang
dirancang dengan baik, bukan landing page startup.

### Larangan (penting)
- Tidak ada gradasi, glow, blur, glassmorphism, atau pola latar dekoratif.
- Tidak ada emoji di antarmuka.
- Tidak ada bayangan tebal. Kartu memakai border 1px, tanpa shadow. Shadow kecil hanya untuk
  dropdown, modal, dan menu yang melayang.
- Tidak ada warna ungu/biru neon, tidak ada teks berwarna-warni.
- Tidak ada hero besar dengan tiga kartu fitur dan tombol "Mulai Sekarang".
- Tidak ada ilustrasi stok, ikon besar dekoratif, atau ikon di setiap judul.
- Tidak ada animasi berlebihan. Transisi maksimal 150ms untuk hover/fokus.
- Tidak ada teks generik seperti "Selamat datang di platform inovatif", "Solusi terbaik",
  "Tingkatkan produktivitas Anda". Tidak ada tanda seru di teks sistem.
- Satu ukuran radius per jenis elemen, jangan semua serba sangat membulat.

### Token warna (definisikan di `resources/css/app.css` dengan `@theme`)
```
--color-ink:        #1C1F1E   teks utama
--color-ink-soft:   #4A504D   teks sekunder
--color-ink-muted:  #686E6B   keterangan, placeholder
--color-line:       #E3E5E1   border default
--color-line-strong:#C9CCC6   border tegas (kartu, pemisah)
--color-line-input: #858B87   border kontrol form: input, select, textarea, checkbox, file-upload
                              (kontras 3.48:1 di atas paper, 3.21:1 di atas canvas)
--color-paper:      #FFFFFF   permukaan kartu
--color-canvas:     #F6F6F3   latar halaman (putih hangat, bukan abu dingin)
--color-subtle:     #EFF0EC   latar baris/hover

--color-brand:      #1F5A47   aksen utama (hijau hutan tua)
--color-brand-hover:#174536
--color-brand-tint: #E7F0EC   latar lembut aksen

Status (versi kalem, teks gelap di atas latar terang):
--color-ok:   #2F6B3A  bg #E8F1E8   terverifikasi
--color-wait: #8A5A00  bg #FBF1DC   diajukan / menunggu
--color-fix:  #A3392B  bg #F9E7E4   perlu revisi / terlambat
--color-idle: #5C625F  bg #EEEFEC   draf / tidak aktif
```
Hanya satu warna aksen. Warna status hanya untuk badge status dan angka status, bukan dekorasi.
Semua teks, termasuk teks kecil, wajib berkontras minimal 4.5:1 terhadap latarnya
(paper, canvas, subtle, atau latar status). Batas kontrol form (border input) minimal 3:1
terhadap paper dan canvas, karena itu kontrol form memakai `line-input`; border kartu dan
pemisah tetap `line`/`line-strong`. Periksa kontras sebelum mengubah atau menambah warna.

### Tipografi
- Font: Public Sans, untuk seluruh UI.
- Ukuran: 13px (keterangan), 14px (teks UI default, tabel), 16px (paragraf publik),
  18px (judul kartu), 22px (judul halaman), 28–32px (judul halaman publik).
- Pengecualian: 11px (`text-micro`) HANYA untuk label ringkas di sidebar tablet. Jangan dipakai
  di tempat lain.
- Ketebalan: 400 dan 600 saja. Judul memakai 600, bukan 700/800.
- Gunakan sentence case: "Laporan bulanan", bukan "Laporan Bulanan" pada tombol dan label.
  Nama menu boleh Title Case singkat.
- Angka dalam tabel memakai `tabular-nums`, rata kanan, dengan jarak ekstra (32px) sebelum
  kolom berikutnya.
- Format Indonesia: `1.250.000`, `Rp 1.250.000`, tanggal `9 Oktober 2026`, bulan `September 2026`.

### Bentuk & jarak
- Radius: 6px untuk input/tombol/badge, 10px untuk kartu dan modal.
- Border: 1px `--color-line`. Kontrol form memakai 1px `--color-line-input`.
- Jarak dasar kelipatan 4px. Padding kartu 20px (desktop), 16px (mobile).
- Tinggi kontrol: 40px (desktop), 44px (mobile, agar mudah disentuh).
- Lebar konten maksimal 1200px untuk area aplikasi, 1080px untuk halaman publik.

### Tata letak gaya aplikasi (responsif)
- **HP (< 768px):** header atas tipis (judul halaman + tombol notifikasi), navigasi bawah tetap
  berisi maksimal 5 menu utama, menu lain di "Lainnya". Tabel berubah menjadi daftar kartu.
  Form satu kolom. Aksi page-header tampil di bawah judul dan ikut tergulir.
  Bar aksi yang menempel di bawah layar hanya untuk halaman form, lewat `<x-form-actions sticky>`.
  Halaman form memakai mode form (`<x-layouts.app focus back="...">`): navigasi bawah dan
  tombol notifikasi disembunyikan, header berisi tombol kembali + judul.
- **Tablet (768–1279px):** sidebar ikon ringkas (lebar 72px) dengan label ringkas 11px
  (lihat Bagian 6) dan tooltip nama menu lengkap saat hover/fokus, konten penuh.
  Tabel tampil dengan kolom penting saja.
- **Desktop (≥ 1280px):** sidebar penuh 248px (logo + nama aplikasi, menu, profil pengguna di bawah),
  header halaman berisi judul + aksi. Tabel lengkap.
- Halaman publik memakai navigasi atas biasa, dengan menu hamburger di HP dan tablet (< 1024px).
  "Masuk" berupa tautan teks biasa, bukan tombol.
- Website publik bergaya buletin resmi: judul tipografis, bagian dibuka garis `line-strong` + judul
  (`publik._section-heading`), angka besar `tabular-nums`. Tanpa hero bergambar atau kartu fitur.
- Foto produk belum ada → petak inisial (2 huruf) berlatar `subtle`, huruf `ink-muted`. Di beranda,
  produk unggulan berfoto didahulukan. Foto sampul (opsional, dari Konten Website) tampil lebar di
  bawah pembuka beranda dengan keterangan dan nama fotografer.
- Tampilkan "Data diperbarui [tanggal]" di beranda dan profil kelompok.
- Footer publik: identitas + "Dikelola oleh Pemerintah Distrik Ransiki" + tempat logo (`x-logo`),
  kontak kantor distrik, daftar OPD.
- Semua layout punya tautan "Lewati ke konten" ke `#konten`. Area login dan halaman masuk `noindex`.
- Logo memakai komponen `logo` (sementara placeholder "SK"); di tablet hanya logo yang tampil.

### Gaya website publik
Berlaku HANYA untuk halaman publik (`resources/views/publik/`, `components/layouts/public`,
`components/public/`). Area Admin/OPD/Kelompok tetap memakai aturan di atas tanpa pengecualian.

Pengecualian dari Larangan di atas, khusus publik:
- Hero lebar penuh di beranda, HANYA dengan foto asli dari Konten Website (bukan foto stok). Tanpa
  foto: latar `forest` polos. Pita judul halaman lain: `forest` polos; Profil Ransiki boleh berfoto.
- Gradasi diizinkan KHUSUS sebagai overlay foto (`.overlay-forest`): desktop forest 85% (0%) → 70%
  (50%) → 20% (100%); HP (< 768px) padat 70%. Teks hero hanya di setengah kiri. Kontras terburuk
  (teks putih di atas bagian foto yang putih) 5,5:1 di titik 50%; gradasi lurus 85%→20% gagal
  (3,3:1) sehingga titik 70% wajib ada. Gradasi lain tetap dilarang.
- Warna tambahan: `forest` #0F2E22 (latar gelap, footer), `forest-soft` #B9C7BF (teks sekunder di
  atas forest, 8,4:1), `forest-line` #2A4A3D (garis di atas forest), `sun` #E9B949 (pita angka,
  tombol utama dan cincin fokus di latar gelap). `ink` di atas `sun` 9,0:1; `sun` di atas `forest`
  8,0:1. DILARANG: teks putih di atas `sun` (1,8:1) dan `sun` sebagai warna teks di latar terang.
  Di atas `sun` semua teks memakai `ink`.
- Label pil (`rounded-full`) untuk jenis, kampung, dan bidang usaha. Kartu tetap radius 10px, tanpa
  bayangan.
- Judul hero `text-hero` 48px (desktop) / `text-hero-sm` 36px (HP).
- Bagian gelap diberi kelas `on-dark` agar cincin fokus berwarna `sun`.

Tetap dilarang di publik: bayangan tebal, emoji, ilustrasi, ikon dekoratif, testimoni, angka
karangan, tombol "Mulai sekarang", teks promosi generik.

Susunan: header putih → (beranda) hero → pita kuning 3 angka asli + "Data diperbarui" → kartu
bidang usaha berfoto (foto bidang dari Admin → foto produk publik terbaru bidang itu → kartu
`forest` polos) → kartu produk unggulan → bagian gelap "Tentang" → footer `forest`. Kartu direktori
tanpa foto: latar `subtle` + inisial `ink-muted`. Profil kelompok: pita `forest` polos; foto produk
tampil utuh di bagian Produk.

Gambar publik: `<picture>` dengan WebP `srcset` + cadangan JPEG (`x-public.picture`). Foto lebar
(sampul, bidang usaha, "Tentang"): WebP 480/960/1600, hero 1600 ≤ ~200 KB (kualitas turun bertahap
70→50). Foto produk: WebP 400/800 + JPEG 400. Hero `fetchpriority="high"`; gambar lain lazy-load.
Varian dibuat saat unggah; foto lama: `php artisan simker:varian-foto`. Tanpa dukungan WebP di
server, otomatis JPEG saja.

Token `forest`/`sun` tidak boleh dipakai di luar view publik (dijaga tes).

### Komponen Blade (buat di awal, pakai di semua halaman)
Di `resources/views/components/`:
`button`, `input`, `select`, `textarea`, `checkbox`, `field` (label + input + error + hint),
`card`, `stat` (angka ringkas), `badge` (status), `table` (+ mode kartu di mobile),
`empty-state`, `modal`, `dropdown`, `page-header`, `alert`, `tabs`, `file-upload`,
`form-actions` (bar tombol form; `sticky` hanya untuk halaman form), `checkbox-group`, `logo`,
`layouts.app` (area login), `layouts.public` (website publik), `layouts.guest` (login).

Halaman tidak boleh menulis ulang gaya tombol/kartu sendiri. Selalu pakai komponen.

### Tabel dan kartu daftar
- Tidak ada tombol "Buka" per baris. Baris yang punya halaman detail bisa diklik seluruhnya:
  `<x-table clickable>` + `<x-table.row href="...">`. Link ada di sel `primary` dan diperluas
  menutupi baris, sehingga tetap satu link yang bisa difokuskan dengan keyboard (cincin fokus
  mengelilingi baris). Hover memakai latar `subtle`, chevron-right kecil `ink-muted` di kanan.
- Tombol aksi lain di dalam baris dihindari; bila terpaksa, beri `relative z-10` agar bisa diklik.
- Pengecualian: daftar tanpa halaman detail (mis. Data Master) memakai sel `actions` berisi
  tombol Ubah/Hapus, dengan tambah/ubah lewat modal (`recordForm` di `resources/js/app.js`).
- Mode kartu di HP: baris atas berisi nama (`primary`) dan badge status (`badge`) sejajar,
  subjudul (`subtitle`, mis. periode) `ink-soft` di bawah nama, sel lain menjadi baris
  label-nilai. Seluruh kartu bisa diketuk, chevron di kanan.

### Form
- Select tanpa nilai terpilih tampil `ink-muted`, sama seperti placeholder input.
- Grup checkbox memakai `checkbox-group`: lebar kolom mengikuti isi dengan jarak tetap,
  satu kolom di HP dan maksimal dua kolom mulai tablet.

### Gaya penulisan teks
- Bahasa Indonesia baku tapi ringan, kalimat pendek.
- Tombol: kata kerja, 1–3 kata. "Simpan", "Ajukan laporan", "Minta revisi", "Verifikasi".
- Pesan error: sebut masalahnya lalu solusinya. "Bulan ini sudah ada laporan. Buka laporan yang ada."
- Keadaan kosong: jelaskan apa yang bisa dilakukan. "Belum ada laporan. Buat laporan pertama
  untuk bulan September 2026."
- Konfirmasi: "Laporan diajukan", bukan "Laporan berhasil diajukan!".

---

## 8. Urutan pengerjaan

Target: versi uji coba ruang lingkup inti dalam 2 minggu.

**Minggu 1**
1. Setup: Sail + MySQL, Tailwind v4, Alpine, Public Sans, Heroicons, Pest. Timezone aplikasi
   `Asia/Jayapura`, locale `id`.
2. Token desain + komponen Blade dasar + tiga layout (app, public, guest). Buat halaman
   `/dev/komponen` (hanya di environment local) untuk meninjau semua komponen.
   Route-nya ada di `routes/dev.php` dan hanya dimuat saat `APP_ENV=local` (bootstrap/app.php).
   Tautan ke halaman ini di menu mana pun hanya ditampilkan bila `Route::has('dev.components.app')`.
3. Migrasi + model + enum + relasi + seeder data contoh (Bagian 10).
4. Login (username/email), middleware role, redirect dasbor per peran, Policy dasar,
   feature test akses.
5. Admin: CRUD data master, CRUD pengguna, CRUD kelompok (termasuk bidang usaha & OPD terkait).
6. Kelompok: profil kelompok, produk, legalitas & izin, unggah lampiran.
7. Website publik: Beranda, Profil Ransiki, Direktori (cari + filter jenis & bidang),
   halaman profil kelompok, Produk Unggulan, Kontak.

**Minggu 2**
8. Laporan bulanan kelompok (form bagian A–H, simpan draf, ajukan, riwayat).
9. Verifikasi OPD (daftar menunggu, detail, minta revisi, verifikasi, catatan).
10. Dasbor Kelompok, OPD, Admin.
11. Notifikasi dalam aplikasi (ikon lonceng + halaman notifikasi).
12. Uji menyeluruh per peran, perbaikan tampilan HP/tablet, `npm run build`, persiapan deploy.

**Diupayakan setelah inti selesai** (urutan prioritas):
status organisasi di UI · pemisahan penginput & pemverifikasi · Berita & Kegiatan ·
peringatan nama ganda · batas waktu & status terlambat · verifikasi profil + riwayat perubahan ·
grafik ekonomi · dasbor legalitas · tindak lanjut intervensi · email undangan/reset ·
ekspor Excel/PDF & impor spreadsheet.

**Di luar lingkup:** alur perizinan lengkap, integrasi sistem eksternal, WhatsApp, peta,
marketplace, PWA/aplikasi mobile, dua bahasa.

---

## 9. Konvensi kode

- Controller tipis. Validasi di Form Request. Otorisasi di Policy. Logika status laporan di
  satu tempat (mis. `App\Actions\Reports\*` atau method di model), bukan tersebar di controller.
- Enum PHP (backed enum) untuk role, status kelompok, status laporan, status izin, kategori masalah.
  Setiap enum punya method `label()` berbahasa Indonesia dan, bila perlu, `badgeColor()`.
- Controller dikelompokkan per area: `App\Http\Controllers\Admin\*`, `Opd\*`, `Kelompok\*`,
  `Publik\*`. Pengecualian: fitur yang sama persis di beberapa area (detail kelompok, CRUD
  produk/izin, unggah file) memakai `App\Http\Controllers\Shared\*`; hak akses sepenuhnya
  ditentukan Policy, bukan oleh controller mana yang dipakai. View bersama di `resources/views/shared/`.
- View dikelompokkan sama: `resources/views/admin/...`, `opd/...`, `kelompok/...`, `publik/...`.
- Hindari N+1: gunakan `with()` dan `withCount()`. Paginasi 20 baris.
- Format angka/tanggal Indonesia lewat helper bersama, bukan ditulis ulang di view.
- Upload: validasi mime + ukuran di Form Request, simpan dengan nama acak, jangan percaya nama file asli.
  Tipe dicek dari isi file (`finfo`), bukan ekstensi. Gambar > 8000 px per sisi ditolak sebelum
  diproses. Gambar diproses `intervention/image` (driver GD): diputar sesuai EXIF, sisi terpanjang
  maks 1600 px, disimpan ulang tanpa metadata (EXIF/GPS hilang). Foto produk selalu JPEG + versi
  kecil 400 px. Input gambar memakai `accept="image/jpeg,image/png"` (iPhone mengonversi HEIC);
  HEIC yang tetap terkirim ditolak dengan pesan cara mengubah pengaturan kamera.
- Unggah lewat XHR satu file per permintaan dengan bilah progres; gambar diperkecil dulu di
  browser (canvas), server tetap memproses ulang. Batas 30 unggahan per menit per akun.
- Jangan menaruh query di Blade.

---

## 10. Data contoh (seeder)

Data contoh harus terlihat realistis namun jelas bukan data resmi:
- 4 OPD: Dinas Pertanian dan Perkebunan, DPMPTSP, Dinkopdag, Dinas Perikanan
  (nama sesuai blueprint klien; akan diganti nama resmi).
- 8 kampung bernama "Kampung Contoh 1" s.d. "Kampung Contoh 8".
- Jenis kelembagaan dan bidang usaha sesuai Bagian 4.
- 25 kelompok dengan kombinasi jenis, bidang usaha (1–3), dan OPD (1–3) yang beragam.
  Beberapa berstatus `inactive` dan satu `closed`.
- Produk contoh: kakao, kopi, pinang, sagu, ikan asap, keripik, dsb.
- Laporan bulanan 3 bulan terakhir dengan status beragam (draft, submitted, revision, verified),
  sebagian kelompok sengaja belum melapor, sebagian `no_activity`, sebagian angka keuangan kosong.
- Akun uji (kata sandi `password`, hanya untuk local):
  `admin` · `opd.pertanian` · `opd.dpmptsp` · `opd.dinkopdag` · `opd.perikanan` ·
  `kelompok1`, `kelompok2` (dua kelompok berbeda, untuk uji akses silang)

---

## 11. Checklist sebelum menyatakan fitur selesai

- [ ] Halaman terbuka tanpa error di lebar 375px, 820px, dan 1440px
- [ ] Hanya memakai komponen dan token desain yang ada
- [ ] Policy/scope memblokir akses peran lain (ada feature test)
- [ ] Validasi form dan pesan error berbahasa Indonesia
- [ ] Keadaan kosong (empty state) ditangani
- [ ] Tidak ada N+1 query yang jelas
- [ ] `sail artisan test` lulus

---

## 12. Deploy ke Hostinger (catatan)

- Butuh paket dengan akses SSH dan Composer (Business ke atas) atau VPS. PHP 8.3+.
- Arahkan document root ke folder `public` (atau simlink `public_html` → `public`).
- `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`,
  `php artisan config:cache route:cache view:cache`.
- Aset dibangun di lokal (`npm run build`) lalu folder `public/build` ikut diunggah;
  Node tidak wajib ada di server.
- File privat tetap di `storage/app/private`, tidak bisa diakses langsung dari URL.
- PHP: ekstensi `gd` (dengan dukungan WebP; tanpa itu gambar publik otomatis JPEG saja), `exif`, `fileinfo` wajib aktif. `upload_max_filesize` dan `post_max_size`
  minimal 12M, `memory_limit` minimal 512M (gambar besar didekode penuh di memori sebelum diperkecil).
- HTTPS wajib: tombol "Salin info masuk" memakai clipboard browser yang hanya penuh berfungsi di HTTPS.
- `/sitemap.xml` dan `/robots.txt` dibuat dinamis (tidak ada file `public/robots.txt`). Gambar pratinjau
  tautan bawaan: `public/og-default.png` (1200×630). Pastikan `APP_URL` benar agar URL Open Graph absolut.
- Cron: `* * * * * php /path/artisan schedule:run >> /dev/null 2>&1` (bila fitur pengingat dipakai).
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Jayapura`.
- Backup database berkala (fitur backup Hostinger + ekspor manual sebelum update besar).
