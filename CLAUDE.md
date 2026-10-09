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
  memeriksa Policy.
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
- `site_settings`: key, value (teks Profil Ransiki, potensi ekonomi, kontak kantor distrik)
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
draft ──ajukan──▶ submitted ──verifikasi──▶ verified
                      │
                      └──minta revisi──▶ revision ──perbaiki & ajukan──▶ submitted
```

- Kelompok hanya bisa mengubah laporan berstatus `draft` atau `revision`.
- Laporan `submitted` terkunci untuk kelompok sampai OPD bertindak.
- Laporan `verified` tidak bisa diubah (tahap inti).
- OPD yang boleh memverifikasi: OPD mana pun yang terhubung dengan kelompok tersebut.
  Admin juga boleh. (Keputusan sementara, menunggu konfirmasi klien.)
- Permintaan revisi wajib disertai catatan.
- Kelompok bisa memilih "Tidak ada aktivitas bulan ini"; bagian lain menjadi opsional.
- Batas pengiriman default: tanggal 10 bulan berikutnya, zona waktu `Asia/Jayapura` (WIT).
  Simpan sebagai konfigurasi, jangan ditulis langsung di banyak tempat.
- Status "belum melapor" dihitung untuk kelompok `active` dan `inactive` yang
  `reporting_starts_on` ≤ periode. Kelompok `closed` tidak dihitung.
  Laporan `submitted` yang belum ditinjau TIDAK dihitung terlambat.

Setiap perubahan status mencatat baris di `report_reviews` dan mengirim notifikasi:
- diajukan → semua user OPD terkait
- minta revisi / terverifikasi → semua user kelompok tersebut

---

## 6. Menu per peran

**Publik:** Beranda · Profil Ransiki · Direktori Kelompok · Produk Unggulan · Kontak · Masuk

**Kelompok:** Dasbor · Profil Kelompok · Produk · Legalitas & Izin · Laporan Bulanan ·
Notifikasi · Akun Saya

**OPD:** Dasbor · Kelompok Binaan · Verifikasi Laporan · Semua Laporan ·
Masalah & Kebutuhan · Notifikasi · Akun Saya

**Admin:** Dasbor Distrik · Semua Kelompok · Semua Laporan · Pengguna ·
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
- Logo memakai komponen `logo` (sementara placeholder "SK"); di tablet hanya logo yang tampil.

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
  `Publik\*`.
- View dikelompokkan sama: `resources/views/admin/...`, `opd/...`, `kelompok/...`, `publik/...`.
- Hindari N+1: gunakan `with()` dan `withCount()`. Paginasi 20 baris.
- Format angka/tanggal Indonesia lewat helper bersama, bukan ditulis ulang di view.
- Upload: validasi mime + ukuran di Form Request, simpan dengan nama acak, jangan percaya nama file asli.
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
- Cron: `* * * * * php /path/artisan schedule:run >> /dev/null 2>&1` (bila fitur pengingat dipakai).
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Jayapura`.
- Backup database berkala (fitur backup Hostinger + ekspor manual sebelum update besar).
