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
- Ikon: satu set saja, Heroicons outline (paket `blade-ui-kit/blade-heroicons`). Pengecualian: ikon bidang usaha di
  website publik memakai SVG Lucide (ISC) yang disalin ke `resources/svg/lucide` (tanpa paket), garis 1,5 seperti
  Heroicons, dirender `x-public.icon`. Pemetaan slug nama bidang → ikon di `config/simker.php` `public_icons`
  (bidang tanpa entri = `default` kotak), dibaca `App\Support\PublicIcons`.
- Font: Public Sans, dipasang lokal melalui npm `@fontsource-variable/public-sans` (tidak memakai
  Google Fonts CDN). Judul h1–h3 website publik: Source Serif 4 (600), lokal lewat
  `@fontsource-variable/source-serif-4` (token `font-serif`, khusus publik).
- Tes: Pest (atau PHPUnit bawaan bila Pest belum terpasang).
- Ekspor/impor: `spatie/simple-excel` (Excel/CSV, memakai openspout 4) dan `barryvdh/laravel-dompdf` (PDF).
- Grafik interaktif: `chart.js` v4 (npm, Paket 3), hanya di halaman Statistik dan dasbor Kelompok.
- Email: SMTP lewat mailer bawaan Laravel (tanpa paket). Lokal memakai Mailpit (layanan Sail di `compose.yaml`):
  semua email tertangkap dan bisa dibaca di http://localhost:8025. Tes memakai mailer `array`.
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
| Laporan bulanan | lihat, verifikasi, minta revisi; isi atas nama kelompok | lihat, verifikasi, minta revisi; isi atas nama kelompok | lihat, buat, ubah & ajukan saat `draft`/`revision` |
| Tindak lanjut masalah & kebutuhan | lihat, ubah status + catatan (pilih OPD penanggung jawab) | lihat, ubah status + catatan (atas nama OPD-nya) | lihat saja |
| Lampiran | mengikuti hak atas induknya | mengikuti hak atas induknya | mengikuti hak atas induknya |
| Riwayat perubahan (tab "Riwayat") | lihat | lihat | – |
| Verifikasi profil ("Tandai profil terverifikasi") | ya | ya | – (status tampil baca-saja) |
| Berita & Kegiatan | kelola | – | – |
| Pengguna | kelola, reset kata sandi, kirim (ulang) undangan email (tidak bisa menonaktifkan/menghapus diri sendiri) | – | – |

Laporan `verified` tidak bisa diubah siapa pun. Kelompok `closed` tidak bisa membuat laporan baru.

### Aturan akses (WAJIB)

- Setiap pembatasan akses dicek di server melalui Policy dan/atau query scope. Menyembunyikan
  menu atau tombol saja tidak cukup.
- Gunakan Policy untuk `Organization`, `MonthlyReport`, `Product`, `License`, `Attachment`, `User`, `ReportIssue`.
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
- `GET /foto-produk/{id}` boleh tanpa login bila produk tampil publik (`Product::publiclyListed()`: produk `is_public`,
  kelompok `is_public` dan aktif atau tidak aktif sementara); selain itu wajib login + Policy, tamu tanpa hak mendapat 404.
  Foto tambahan di detail produk: lampiran produk bervisibilitas `public` bertipe JPG/PNG, lewat
  `GET /foto-produk/{id}/lampiran/{lampiran}` dengan syarat yang sama (lampiran privat/PDF/milik produk lain → 404).
- Header file: `X-Content-Type-Options: nosniff`; PDF tampil inline dengan
  `Content-Security-Policy: sandbox`, plus tombol "Unduh" (`Content-Disposition: attachment`).
- Setiap fitur baru yang menyentuh data wajib punya feature test akses: pemilik bisa, peran lain
  yang tidak berhak ditolak.

### Route

```
/                       publik
/direktori, /direktori/{slug}, /produk, /produk/{slug}, /profil, /kontak, /berita, /berita/{slug}
/login (GET, POST), /logout (POST saja)
/lupa-kata-sandi, /atur-ulang-kata-sandi/{token}, /undangan/{user}/{token}   guest
/akun/kata-sandi        auth + active (semua peran; juga halaman ganti kata sandi wajib)
/admin/...              auth + active + password.changed + role:admin
/opd/...                auth + active + password.changed + role:opd
/kelompok/...           auth + active + password.changed + role:kelompok
```

Setelah login, arahkan pengguna ke dasbor sesuai perannya. Halaman tujuan sebelum login hanya
dipakai bila berada di area perannya sendiri.

### Website publik (data)
- Satu pintu data publik: scope `Organization::publiclyListed()` (status `active` atau `inactive` +
  `is_public`; `closed` tidak pernah) dan `Product::publiclyListed()` (produk `is_public` milik kelompok
  yang terdaftar publik). Dipakai semua halaman publik, angka beranda, sitemap, dan Open Graph. Kelompok
  lain → 404, juga bila dulu publik. Kelompok `inactive` diberi label pil "Tidak aktif sementara".
- Query publik memilih kolom publik saja (`select`): nama, jenis, kampung, bidang usaha, deskripsi,
  tahun berdiri, kapasitas produksi, pasar. Tidak pernah tampil: jumlah anggota, tenaga kerja, aset,
  fasilitas, peralatan, keuangan, laporan, masalah, lampiran, akun, nomor izin.
- Kontak (ketua, telepon, email, alamat) baru diambil dari database bila `show_contact_publicly`.
- Bagian Legalitas hanya tampil bila status hukum `registered` (ditulis "terdaftar") atau ada izin
  `issued` (jenisnya saja). Status lain tidak pernah tampil di publik.
- Berita & Kegiatan: satu pintu `Post::publiclyListed()` (status `published` + `published_at` ≤ sekarang WIT);
  draf dan terjadwal → 404, juga foto sampulnya (`/foto-berita/{id}`, kecuali Admin). Kelompok terkait hanya yang
  `Organization::publiclyListed()`. Isi Markdown dirender `Format::markdown` (html_input strip,
  allow_unsafe_links false), juga untuk tombol "Pratinjau" di form Admin. Beranda menampilkan 3 berita terbaru;
  halaman berita memakai `og:type` article + ringkasan sebagai description + foto sampul sebagai og:image.
- Direktori (`/direktori`): satu tempat logika `App\Support\DirectorySearch` (publiclyListed, kolom publik saja). Query
  string `q` (nama kelompok, produk publik, kampung), `jenis[]`, `bidang[]`, `kampung[]` (ATAU dalam satu dimensi, DAN
  antardimensi; nilai tunggal lama `?bidang=5` tetap diterima), `urut` (nama/terbaru/produk), `page` (12 per halaman).
  Facet = COUNT DISTINCT per dimensi mengikuti filter dimensi lain. Judul/description dinamis, canonical tanpa q/urut.
  Form GET biasa (jalan tanpa JS); Alpine `directory` (`resources/js/directory.js`) memuat ulang hasil lewat fetch
  (debounce cari 300 ms, pushState, tukar `[data-swap]` + `[data-facet]`), bottom sheet filter < 1024px, tampilan
  Kartu/Daftar (varian CSS `list-view`, localStorage). Foto kartu: foto produk publik → foto bidang usaha → panel cream
  berinisial (belum ada kolom foto profil kelompok). Sorotan kata kunci `bg-highlight` (sun pucat, bukan `sun`).
  Efek lebih ringan dari Beranda: `x-reveal.stagger.quick` (sekali per kartu, pemicu 10%, 16/24px, 600ms, jeda 60ms),
  intro banner + kotak cari sekali, ganti hasil: memudar 150ms lalu muncul berjeda 40ms (posisi gulir dijaga, paginasi
  gulir halus ke bar hasil), pil filter skala 0,9 ↔ 1, bottom sheet meluncur 250ms. Angka di variabel `--reveal-quick-*`,
  `--swap-*`, `--chip-duration`, `--sheet-duration` (app.css). Panel filter, header, bar hasil tanpa animasi.
- Produk unggulan (`/produk`): satu tempat logika `App\Support\ProductSearch` (`Product::publiclyListed()`, kolom publik saja).
  Query string `q` (nama produk, nama kelompok, kampung), `bidang`, `kampung` (satu nilai), `urut` (pilihan = Pilihan distrik
  dulu [bawaan] / terbaru / nama), `page` (16 per halaman). Pil bidang (radio bergaya pil, ikon `PublicIcons`) dengan jumlah
  produk unik mengikuti cari + kampung; deretan "Pilihan distrik" (`is_featured`, maks 8, foto 4:5, panah `scrollRow`) hanya
  di halaman 1 tanpa cari/filter. Muat ulang otomatis memakai Alpine `directory` yang sama dengan Direktori
  (`data-default-sort="pilihan"`). Kartu `publik/products/_card` (foto 1:1 `rounded-tile`, tanpa foto: cream + ikon bidang
  clay). BUKAN toko: tanpa harga, keranjang, atau checkout.
- Detail produk (`/produk/{slug}`, `publik/products/show`): galeri (foto produk + lampiran publik, thumbnail, perbesar lewat
  `<dialog>`, Alpine `productGallery`), info + kartu kelompok pembuat, "Hubungi kelompok" (wa.me + tel:) HANYA bila
  `show_contact_publicly` (telepon baru diambil saat itu), selain itu "Lihat profil kelompok"; bagikan WhatsApp + salin tautan;
  produk lain dari kelompok yang sama (maks 4) dan produk serupa (bidang sama, kelompok lain, maks 4). JSON-LD `Product`
  TANPA `offers`/harga (name, image, description, brand = kelompok). HP: bar tombol utama menempel (`stickyCta`) setelah
  tombolnya tergulir lewat, hilang saat footer terlihat. Kartu produk di Beranda dan profil kelompok menaut ke sini; sitemap
  memuat semua produk publik.
- Berita (`/berita`): satu tempat logika `App\Support\PostListing` (`Post::publiclyListed()`). Query string `kategori`, `q`
  (judul), `bulan` (YYYY-MM, tidak sah = diabaikan), `page` (10 per halaman). Kop editorial cream "Kabar Distrik Ransiki" +
  baris tanggal hari ini; bar alat menempel (tab kategori berjumlah + cari). Sorotan (hanya tanpa filter): berita utama
  (terbaru berfoto, tanpa itu terbaru) dengan teks di atas foto (`.overlay-band-bottom`, putih 85% ≥ 5,3:1) + dua terbaru;
  dihitung di setiap halaman agar daftar terbaru tidak pernah mengulangnya, tampil di halaman 1. Agenda kegiatan (post
  Kegiatan, maks 8; ada `event_date` setelah hari ini WIT → judul "Agenda kegiatan", yang akan datang dulu dari yang paling
  dekat, selain itu "Kegiatan terbaru"; blok tanggal 52 × 56px (HP 46 × 50px) = `Post::eventDay()` (tanggal kegiatan, tanpa
  itu tanggal terbit); judul 2 baris bertinggi tetap, lokasi 2 baris: kampung lalu kelompok). Arsip bulan + Kategori di panel
  kanan menempel (HP: pil geser). Detail: garis progres membaca 3px `sun` di bawah header (`readingProgress`, tanpa
  transisi; pengecualian `sun` atas permintaan), judul 44px (HP 30px), baris penerbit, foto 16:9 sudut 20px, drop cap
  (`.prose-article.drop-cap`), rel bagikan menempel mulai 1024px (HP: baris tombol), Sebelumnya/Berikutnya berthumbnail,
  "Baca juga" (kategori sama dulu, lalu terbaru).
- Badge "Profil diverifikasi OPD" (pil) di direktori dan profil publik bila `profile_status = verified`; tidak
  pernah memblokir tampil publik.
- Angka beranda dihitung unik dan di-cache 10 menit (`App\Support\PublicDirectory`). Cache hanya
  boleh berisi array/teks: Laravel menolak membuka objek dari cache (`cache.serializable_classes`).

### Login dan session
- Login dengan username ATAU email + kata sandi. Username disimpan dan dicocokkan dalam huruf
  kecil (trim + lowercase), termasuk di seeder dan saat Admin membuat akun.
- Tidak ada daftar akun sendiri. Reset oleh Admin (kata sandi sementara) atau, untuk akun aktif yang punya
  email, "Lupa kata sandi?" di halaman masuk (password broker Laravel, `Auth\PasswordResetController`, tautan
  berlaku `auth.passwords.users.expire` = 60 menit). Jawaban SELALU "Jika email terdaftar, tautan telah dikirim"
  dan email dikirim setelah respons (`dispatch(...)->afterResponse()`), agar keberadaan email tidak bocor.
  Throttle `password-reset`: 5/menit per IP, 3/jam per email. Akun tanpa email: "Hubungi Admin distrik".
- Undangan akun (Admin, saat menambah akun berisi email: "Kirim undangan lewat email" atau kata sandi sementara;
  "Kirim ulang undangan" di detail pengguna): `App\Actions\Users\SendInvitation`, tautan bertanda tangan
  (`URL::temporarySignedRoute`) berlaku 72 jam. Sekali pakai lewat hash SHA-256 token di `users.invitation_token`
  (dikosongkan setelah dipakai, saat kirim ulang diganti, saat reset kata sandi dibatalkan). Kedaluwarsa/terpakai/
  diubah → halaman "Undangan tidak berlaku" dengan alasannya. Setelah membuat kata sandi, pengguna langsung masuk.
- Template email (`resources/views/emails/*`, HTML + teks): Bahasa Indonesia, logo teks SIM-KER, tanpa gambar,
  gaya inline. Gagal kirim (SMTP salah) tidak membatalkan akun: pesan error + kirim ulang.
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
- Satuan (`products.unit`, `report_items.production_unit`/`sales_unit`) dipilih dari daftar umum
  `App\Support\Units`: kg, ton, gram, liter, ekor, ikat, bungkus, karung, buah, lembar, lainnya.
  Isian bebas hanya bila "lainnya" (komponen `unit-input`). Disimpan huruf kecil tanpa spasi tepi
  (`Units::normalize`); data lama di luar daftar tetap terbaca sebagai "lainnya" + teks aslinya.
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
- Satu kelompok boleh punya lebih dari satu akun (beberapa user dengan organization_id sama). Admin
  menambahnya dari tab Akun detail kelompok (tombol "Tambah akun" di aksi header). Semua notifikasi untuk
  kelompok dikirim ke semua akun aktifnya.

### Kelompok
- `organizations`: id, slug, name, institution_type_id, village_id, address, founded_year,
  leader_name, phone, email, members_count, description,
  production_capacity, markets, workforce_count, assets, facilities, equipment,
  legal_status (enum LegalStatus: unknown, none, in_process, registered; default unknown),
  legal_number (teks, nullable), legal_notes (text, nullable),
  status (enum OrganizationStatus: active "Aktif", inactive "Tidak aktif sementara", closed "Ditutup";
  default active),
  reporting_starts_on (date, awal bulan; kelompok tidak dianggap terlambat sebelum tanggal ini),
  is_public (bool, default false), show_contact_publicly (bool, default false),
  created_by, updated_by, timestamps
- Peringatan data ganda (Paket 4): kemiripan nama di PHP (`App\Support\SimilarNames`): huruf kecil, tanda baca
  dan spasi ganda dibuang, buang awalan umum (kelompok, kelompok tani, kelompok nelayan, kub, bumdes, umkm, koperasi;
  "kel." = kelompok). "Tani" dan "nelayan" adalah penanda jenis: bila berbeda, tidak mirip. Angka di nama berbeda →
  tidak mirip. Mirip bila sisa nama sama, similar_text ≥ `simker.duplicates.min_similarity` (85%), atau
  Levenshtein ≤ 2 untuk sisa nama ≥ 8 huruf. SATU tempat untuk kandidat: `App\Support\DuplicateCandidates`
  (form, halaman data ganda, pratinjau impor). Mirip + kampung sama = peringatan KUAT; kampung lain = RINGAN.
  Saat Admin menambah, atau mengubah nama/kampung: peringatan kuat → kembali ke form dengan daftar kandidat (nama,
  jenis, kampung, tautan) + centang "Saya sudah memeriksa, tetap simpan"; peringatan ringan → langsung disimpan,
  kandidat tampil di detail kelompok. Tidak pernah memblokir penyimpanan.
- "Kemungkinan data ganda" (Admin, menu Kelompok > tab "Kemungkinan data ganda"): `/admin/kelompok/data-ganda`, daftar
  pasangan (kuat dulu; pasangan yang salah satunya `closed` tidak tampil). "Bukan data ganda" menyimpan pasangan di
  `duplicate_dismissals` (organization_id = id kecil, other_organization_id = id besar, user_id, created_at; unik)
  sehingga tidak muncul lagi di halaman maupun peringatan form. TIDAK ada gabung otomatis: Admin membuka kedua
  kelompok dan memutuskan sendiri (mis. ubah status jadi Ditutup dengan alasan).
- URL area login memakai ID (`/admin/kelompok/12`); slug hanya untuk halaman publik.
- Status kelompok diisi di form hanya saat menambah. Setelah itu hanya Admin yang mengubahnya, lewat menu
  "…" di detail kelompok (`PATCH /admin/kelompok/{id}/status`, `App\Actions\Organizations\ChangeOrganizationStatus`),
  dengan alasan wajib (min 5 karakter). Riwayat di `organization_status_changes`: organization_id, user_id,
  from_status, to_status, reason, created_at (tidak pernah diubah); tampil di kartu "Publikasi & pelaporan"
  tab Profil (Admin/OPD saja). `closed`: tidak bisa membuat laporan baru, tidak wajib lapor, tidak tampil
  publik, data tetap terlihat Admin/OPD. `inactive`: tetap bisa masuk dan tetap wajib lapor.
- Verifikasi profil (Paket 5): `profile_status` (enum ProfileStatus: unverified "Belum diverifikasi", verified
  "Profil terverifikasi", needs_review "Perlu verifikasi ulang"), `profile_verified_at`, `profile_verified_by`,
  `profile_changed_fields` (json, kolom yang berubah sejak verifikasi), `profile_changed_at`. Admin/OPD pembina
  menandai terverifikasi dari kartu "Publikasi & pelaporan" (`POST /{area}/kelompok/{id}/verifikasi-profil`, Policy
  `verifyProfile`). Bila AKUN KELOMPOK mengubah field penting (`App\Support\ProfileReview::FIELDS`: nama, jenis,
  kampung, ketua, telepon, email, alamat, deskripsi, bidang usaha) pada profil terverifikasi → needs_review +
  notifikasi `ProfileNeedsReview` ke akun OPD pembina aktif (tanpa OPD → Admin), sekali saat status berubah.
  Perubahan oleh Admin/OPD atau field lain tidak memicu. Dicek di `SaveOrganization` (satu-satunya jalur ubah
  profil). Antrean "Menunggu verifikasi profil" `/{area}/kelompok/verifikasi-profil` (tab menu Kelompok) (visibleTo, tanpa kelompok `closed`).
- `change_logs` (riwayat, tidak pernah diubah): id, organization_id, loggable_type (alias morph), loggable_id,
  subject ("Profil", "Produk: Kakao", "Izin: PIRT"), action (enum ChangeAction: created, updated, deleted), field,
  old_value, new_value (teks siap tampil: nama kampung, label enum, Ya/Tidak), user_id (NULL = "Sistem"), created_at.
  Diisi `App\Observers\ChangeLogObserver` (Organization, Product, License) lewat `App\Support\ChangeLogger`; hanya
  kolom di `ChangeLogger::FIELDS` (kolom teknis, path foto, dan isi file tidak pernah dicatat). Bidang usaha dan OPD
  pembina (pivot) dicatat `SaveOrganization`. Tab "Riwayat" detail kelompok (`/{area}/kelompok/{id}/riwayat`,
  Policy `viewHistory`: Admin + OPD pembina; OPD lain 404, kelompok 403).
- `organization_sector` (pivot): organization_id, business_sector_id
  Primary key gabungan + index tunggal pada `business_sector_id`.
- `organization_opd` (pivot): organization_id, opd_id
  Primary key gabungan + index tunggal pada `opd_id` (dipakai filter data per OPD).
  Satu kelompok boleh punya banyak bidang usaha dan banyak OPD.
  Hubungan OPD ditetapkan manual oleh Admin, tidak otomatis dari bidang usaha.
- `licenses` (perizinan): id, organization_id, type, number, status
  (enum: none, in_process, issued, needs_fix), submitted_on, issued_on,
  expires_on (date, nullable; label "Berlaku sampai", tidak boleh sebelum issued_on; NULL = tidak diketahui,
  tidak pernah dianggap habis), notes
- `products`: id, organization_id, name, slug (unik; dibuat sekali dari nama saat produk dibuat, tidak berubah saat ganti
  nama; untuk `/produk/{slug}`), description, unit, capacity, is_featured, is_public,
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
  entered_by (user yang membuat laporan: akun kelompok, atau OPD/Admin yang mengisi atas nama kelompok),
  submitted_at, submitted_by, verified_at, verified_by, timestamps
  UNIQUE (organization_id, period): satu laporan per kelompok per bulan.
  `profit` tidak diisi pengguna: dihitung `income_sales + income_other − costs` dan disimpan saat
  laporan disimpan (model event). NULL bila salah satu komponen NULL. Di form tampil baca-saja
  dengan keterangan "dihitung otomatis".
- `report_items` (produksi & penjualan per produk): id, monthly_report_id, product_id (nullable),
  product_name, production_qty, production_unit, sales_qty, sales_unit, revenue
- `report_issues` (masalah & kebutuhan): id, monthly_report_id, kind (enum: problem, support),
  category, description, follow_up_status (enum FollowUpStatus, default `new`), follow_up_opd_id (OPD penanggung
  jawab terkini, nullable), follow_up_at (perubahan status terakhir). Status terkini disalin dari riwayat agar
  query ringan. Simpan draf memperbarui baris yang membawa `id` miliknya (bukan hapus-buat ulang) agar riwayat
  tindak lanjut tetap tersambung saat laporan direvisi.
- `issue_follow_ups` (riwayat tindak lanjut, tidak pernah diubah): id, report_issue_id, user_id, opd_id (penanggung
  jawab), from_status, to_status, note (wajib), acted_on (tanggal tindakan, ≤ hari ini), created_at.
  FollowUpStatus: new "Baru", reviewed "Ditinjau", planned "Direncanakan", in_progress "Sedang ditindaklanjuti",
  done "Selesai", unable "Tidak dapat ditindaklanjuti". OPD yang dipakai sebagai penanggung jawab tidak bisa dihapus
  dari Data Master.
  Kategori masalah: modal, bahan_baku, peralatan, sdm, pemasaran, transportasi, legalitas,
  produksi, lainnya
  Kategori kebutuhan: bantuan_alat, pelatihan, modal, pendampingan, pemasaran, legalitas,
  akses_pasar, lainnya
- `report_reviews` (riwayat): id, monthly_report_id, user_id,
  action (enum: submitted, revision_requested, verified, comment), note, created_at
  Tabel ini sekaligus menjadi riwayat audit laporan.

### Berita & Kegiatan (Paket 5)
- `posts`: id, title, slug (unik; kosong = dari judul), category (enum PostCategory: berita, kegiatan), summary
  (≤ 300, untuk kartu + meta description), body (Markdown), cover_photo_path (nullable; disk `local`, diproses seperti
  foto sampul: WebP 480/960/1600 + JPEG), status (enum PostStatus: draft "Draf", published "Terbit"), published_at
  (terbit tanpa tanggal = sekarang; tanggal ke depan = terjadwal), event_date (date, nullable; "Tanggal kegiatan", hanya
  kategori Kegiatan, kategori lain dikosongkan saat simpan), created_by, updated_by, timestamps.
- `organization_post` (pivot): post_id, organization_id (kelompok terkait, opsional, banyak).
- Admin: menu Website > tab "Berita & kegiatan" `/admin/website/berita` (filter kategori + status Draf/Terjadwal/Sudah tampil). Foto
  sampul diunggah (XHR) setelah berita disimpan pertama kali.

### Ekspor & impor (Paket 2)
- Semua ekspor memakai `Organization::visibleTo` / Policy yang sama dengan layar; OPD hanya kelompok binaan,
  termasuk bila parameter URL dimanipulasi.
- Excel lewat `App\Support\Exports\Spreadsheet`: NULL = sel kosong (bukan 0), teks diawali `= + - @` diberi
  apostrof (cegah formula injection). PDF lewat `App\Support\Exports\Pdf` (view `shared/exports/*`): kepala
  judul + periode + "Dicetak pada … WIT oleh …", kaki nomor halaman + "Angka resmi hanya dari laporan
  terverifikasi", hitam-putih A4. Nomor halaman ditulis lewat kanvas dompdf (CSS `counter(pages)` tidak jalan).
- Daftar kelompok: `GET /{area}/kelompok/unduh` (filter daftar ikut) → `kelompok-Y-m-d.xlsx`.
- Rekap per periode: `GET /{area}/laporan/rekap?periode=Y-m&format=xlsx|pdf` (modal "Unduh rekap" di Semua
  Laporan; periode tidak sah → bulan wajib lapor) → `rekap-laporan-Y-m.*`, `App\Support\Exports\ReportRecap`:
  status per kelompok wajib lapor (`ReportingState`), angka hanya dari laporan `verified` dengan "diisi X dari
  Y kelompok", produksi/penjualan per produk + satuan.
- PDF satu laporan: `GET /{admin|opd|kelompok}/laporan/{id}/pdf` (Policy `view`, tombol "Unduh PDF" di detail).
- Impor kelompok (Admin): `/admin/kelompok/impor` → template (sheet Data + Petunjuk dari data master) → unggah
  .xlsx/.csv (maks 2 MB, 500 baris, tipe dicek finfo, CSV `;` atau `,`) → pratinjau Siap/Peringatan/Gagal
  (`App\Support\Imports\OrganizationImport` + `RowChecker`; master dicocokkan tanpa peduli huruf/spasi; data ganda
  memakai `DuplicateCandidates` + `SimilarNames` untuk database dan baris lain di file: mirip/sama di kampung yang
  sama = Peringatan, dicentang per baris; mirip di kampung lain = catatan saja, baris tetap Siap) → `ImportOrganizations` dalam
  SATU transaksi (status active, is_public false, created_by admin, tanpa akun) → hasil + unduh baris gagal.
  File sementara di disk `local` `imports/{uuid}` milik pengunggah, dibersihkan setelah 1 hari. Contoh file:
  `storage/app/samples/` (buat ulang dengan `make.php` di folder itu).

### Statistik (Paket 3)
- Statistik (Admin & OPD, menu Dasbor): `/{area}/dasbor/ekonomi` (tab Ekonomi) dan `/{area}/dasbor/legalitas` (tab
  Legalitas), `GET /{area}/dasbor/legalitas/unduh` → `rekap-legalitas-Y-m-d.xlsx` (`App\Support\Exports\LegalRecap`).
- SATU service untuk semua angka: `App\Support\Dashboards\Statistics` (juga grafik dasbor Kelompok). Kelompok =
  `Organization::visibleTo` + filter `type`, `village`, `sector`, `opd` (khusus Admin; untuk OPD diabaikan di
  server). Agregasi di database; kelompok dihitung unik (filter lewat subquery/EXISTS, bukan join).
  `App\Support\Dashboards\StatisticsCharts` menyusun data grafik + tabel untuk komponen `x-chart`.
- Rentang `?dari=Y-m&sampai=Y-m`: default 12 bulan sampai bulan wajib lapor (dipotong di `reporting_starts_on`
  paling awal), maks 36 bulan; nilai tidak sah → default.
- Ekonomi: tren pendapatan penjualan (garis; bulan tanpa isian = null/celah, cakupan "Diisi X dari Y kelompok"
  wajib lapor), kepatuhan pelaporan per bulan (bertumpuk; logika `ReportingState`: terverifikasi / menunggu /
  perlu revisi / terlambat / belum diajukan), pendapatan per bidang usaha (catatan wajib: kelompok berbidang
  ganda dihitung di setiap bidangnya; TIDAK ada total gabungan), produksi per produk + satuan (tabel, urut
  terbesar), masalah & kebutuhan per kategori (jumlah laporan). Semua hanya dari laporan `verified`, kecuali
  kepatuhan pelaporan.
- Legalitas (per kelompok unik): punya izin terbit, izin diproses, izin perlu perbaikan, belum punya izin
  (= tidak ada izin issued/in_process/needs_fix). Satu kelompok bisa masuk beberapa kartu. Rincian per jenis
  dan per kampung, daftar tindakan (perlu perbaikan, belum punya izin → tab Izin kelompok), dan "Akan habis
  dalam 60 hari" (izin `issued` dengan `expires_on` antara hari ini dan +60 hari).
- Bulan "sementara" di tren pendapatan (Admin, OPD, Kelompok; `Statistics::isProvisional`): belum lewat tenggat +
  `simker.statistics.provisional_days_after_deadline` (14) hari, ATAU laporan terverifikasi < `provisional_min_coverage`
  (0,8) dari kelompok wajib lapor bulan itu. Segmen menuju bulan itu putus-putus, titik berlubang, tooltip "Sementara:
  baru X dari Y kelompok terverifikasi", catatan "Garis putus-putus = data bulan itu belum lengkap." (hanya bila ada
  bulan sementara yang bernilai), kolom tabel Keterangan "Sementara".
- Dasbor Kelompok: grafik pendapatan dan produksi 12 bulan (paling banyak 2 produk + satuan terbesar), dua kartu
  sama tinggi di bawah kartu Riwayat laporan yang selebar grid. Produksi: legenda produk + satuan, judul sumbu Y =
  satuan; satuan berbeda → grafik kecil terpisah (panel) dalam satu kartu, tidak pernah satu sumbu untuk dua satuan.
- Tabel produksi di HP: kartu dengan nama tebal lalu "Jumlah: 1.384 ekor" dan "Kelompok: 5" (`x-table.cell inline`).

### Konten & notifikasi
- `hero_slides` (foto hero beranda, Konten Website > tab Beranda, maks `HeroSlide::MAX` = 3, Policy `HeroSlidePolicy`
  hanya Admin): photo_path (JPEG 1920 tanpa EXIF; varian WebP 640/1280/1920 = `ImageVariants::HERO`; file asli tidak
  disimpan), caption (≤ 100), alt_text (wajib), focus (enum HeroFocus atas/tengah/bawah → object-position), position,
  is_active, created_by, updated_by. Unggah: JPG/PNG/WebP, maks 8 MB, lebar < 1280 px ditolak, 1280–1599 px diterima dengan peringatan kuning
  (`session('warning')`, "Foto mungkin kurang tajam di layar lebar"), form biasa (bukan XHR, tidak diperkecil di
  browser) dengan pratinjau. Validasi gagal: ringkasan error di atas tombol simpan form itu (`data-form-errors`). Isian tiap form slide berawalan sendiri (`baru[...]`,
  `slide_{id}[...]`). Foto: `GET /foto-hero/{id}` (slide nonaktif hanya Admin). Tanpa slide aktif: satu foto bawaan
  `resources/aset/hero-bawaan*` (`/foto-hero/bawaan`, foto sementara; ganti dengan foto asli Ransiki), tidak pernah
  foto produk kelompok.
- `site_settings`: key, value. Kunci di `SiteSetting::KEYS`: hero_title, hero_title_accent, home_intro, about_title (bawaan di
  `SiteSetting::DEFAULTS` bila kosong), profile_body,
  office_address,
  office_phone, office_whatsapp, office_email, office_hours, cover_photo_path, cover_caption,
  cover_credit. Di-cache sampai disimpan ulang.
- Profil Ransiki (`/profil`, Website > tab "Profil Ransiki" `/admin/website/profil`): banner foto (`profile_photo_path`
  → foto sampul → foto bawaan; juga og:image), navigasi dalam halaman yang menempel (`sectionNav`, `resources/js/section-nav.js`),
  Sekilas (`profile_intro` serif + `profile_body` Markdown, kartu cream "Data singkat" dari `SiteSetting::PROFILE_FACT_KEYS` +
  jumlah kampung Data Master; kosong = baris tidak tampil, tanpa nilai bawaan), angka ringkas (`publik._stats`), Potensi
  ekonomi (`App\Support\PotentialBlocks`, kunci `potential_{1..3}_*`, foto `/foto-potensi/{n}`), Kampung
  (`PublicDirectory::villages()`, hanya kelompok publik: daftar indeks urut jumlah kelompok terbanyak lalu abjad, nama tanpa
  awalan "Kampung", batang `brand` sebanding kampung terbanyak yang tumbuh dari kiri `.village-bar` 900ms ikut x-reveal, maks 3
  ikon bidang clay; desktop 4 + 8 dengan ringkasan menempel di kiri; kampung tanpa kelompok di akhir tanpa tautan), Kantor distrik (`office_*` + `office_photo_path`). Jangan mengisi
  luas wilayah/penduduk dengan angka karangan. Gaya: judul banner `text-hero-lg` 56px (48 tablet, 36 HP), judul bagian 40px
  (28 HP) `tracking-[-0.01em]`, banner 70vh maks 640px dengan zoom pelan saat dibuka (`.banner-zoom`, 1,06 → 1, 1,6 detik),
  Data singkat berupa bento (ubin cream `rounded-tile`, ubin Jumlah kampung forest), angka ringkas berupa 4 ubin, blok
  potensi foto 5:4 + kartu putih menumpuk 32px, nav dalam halaman berupa pil. PENGECUALIAN Bagian 7: nav itu memakai
  latar putih semi transparan + `backdrop-blur-[12px]` (atas permintaan); blur lain tetap dilarang.
- `notifications`: tabel bawaan Laravel (database notifications)
- (diupayakan) `posts` untuk Berita & Kegiatan

### Aturan angka
- Kolom angka laporan nullable. `NULL` = tidak diketahui/tidak diisi. `0` = nol yang dikonfirmasi.
  Jangan pernah mengubah NULL menjadi 0.
- Saat menjumlahkan, tampilkan keterangan bila sebagian data kosong
  (mis. "diisi 18 dari 25 kelompok").
- Jangan menjumlahkan produksi dengan satuan berbeda. Kelompokkan per produk + satuan
  (keduanya dibandingkan `LOWER(TRIM(...))`).
- Statistik resmi di dasbor hanya dari laporan berstatus `verified`.
- Dasbor (`App\Support\Dashboards\*`): "bulan wajib lapor" = bulan lalu; pilihan bulan `?periode=Y-m`
  dari `reporting_starts_on` paling awal sampai bulan lalu, nilai tidak sah kembali ke default.
  Kelompok wajib lapor = scope `Organization::obligatedFor($period)`. Status pelaporan per kelompok:
  terverifikasi / menunggu / perlu revisi / draf / belum melapor (belum ada baris laporan).
  Setiap angka dasbor menaut ke daftar yang tersaring. Belum memakai cache.
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
- Isi laporan atas nama kelompok (Paket 4, kelompok tanpa HP/akun): OPD pembina dan Admin, tombol "Isi laporan" di
  tab Laporan detail kelompok (modal pilih bulan) → `POST /{area}/kelompok/{id}/laporan` → form yang sama
  (`/{area}/laporan/{id}/ubah`, `PATCH .../draf`, `POST .../ajukan`, `Shared\ReportEntryController`,
  `App\Actions\Reports\CreateReport`). `entered_by` = pengisi; setiap akun OPD/Admin yang menyimpan isian
  tercatat SEKALI di `report_reviews` (aksi `entered` "Diisi atas nama kelompok"). Detail menampilkan
  "Diisi oleh <nama> (<OPD>)". Admin/OPD boleh mengubah laporan `draft`/`revision` yang dibuat atas nama kelompok
  atau yang pernah diajukan; draf yang sedang diisi akun kelompok tetap 404 bagi mereka.
- Pemisahan tugas (`simker.verification.separate_duties`, default true): akun yang membuat (`entered_by`), mengisi
  atas nama (`entered`), atau mengajukan (`submitted`) laporan TIDAK boleh memverifikasi / meminta revisi laporan
  itu (`MonthlyReport::wasHandledBy`, dicek di Policy `verify` → 403). Verifikasi oleh petugas OPD lain yang
  terhubung atau Admin. Penginput melihat "Perlu diverifikasi oleh Admin atau petugas OPD lain" (detail + antrean).
  Antrean verifikasi Admin (`/admin/laporan/verifikasi`, menu Laporan > tab "Perlu diverifikasi"): laporan `submitted` yang tidak bisa
  diverifikasi akun OPD aktif mana pun (`MonthlyReport::needsAdminVerification`: tanpa OPD pembina, atau semua
  petugasnya ikut mengisi/mengajukan). Notifikasi pengajuan tidak dikirim ke penginput; bila tidak ada petugas OPD
  yang berhak, ke Admin.
- Laporan `verified` tidak bisa diubah (tahap inti). Lampiran laporan berstatus `verified` tidak
  boleh dihapus siapa pun, termasuk Admin.
- OPD yang boleh memverifikasi: OPD mana pun yang terhubung dengan kelompok tersebut.
  Admin juga boleh, selalu dengan aturan pemisahan tugas di atas. (Keputusan sementara, menunggu konfirmasi klien.)
- Permintaan revisi wajib disertai catatan (min 10 karakter); catatan verifikasi opsional.
- Verifikasi final bagi OPD. Hanya Admin yang bisa "Buka kembali untuk revisi" (verified → revision,
  aksi riwayat `reopened`, alasan wajib min 10 karakter). `verified_at`/`verified_by` dikosongkan;
  riwayat tetap menyimpan verifikasi lama. Alasannya tampil di form kelompok seperti catatan revisi.
- Logika tindakan peninjau ada di `App\Actions\Reports\ReviewReport` (transaksi + `lockForUpdate`):
  hak lihat (404), lalu status, lalu hak tindakan. Bila peninjau lain bertindak lebih dulu, yang kedua
  mendapat pesan "Laporan ini sudah ditinjau oleh …" tanpa baris riwayat ganda.
- Draf kelompok yang belum pernah diajukan (`submitted_at` kosong) tampil di daftar Admin/OPD sebagai
  "Draf, belum diajukan" tanpa tautan; isinya (termasuk lampiran) 404 bagi Admin/OPD. Pengecualian: draf yang
  dibuat OPD/Admin atas nama kelompok (tautan baris memakai Policy `view`).
- Antrean verifikasi OPD (`/opd/laporan/verifikasi`): laporan `submitted`, urut dari pengajuan TERAKHIR yang
  paling lama (dari `report_reviews`), dengan penanda "Diajukan ulang".
- "Masalah & kebutuhan" (`/opd/laporan/masalah`, `/admin/laporan/masalah`, tab menu Laporan): isian bagian E/F hanya dari laporan
  `submitted`/`verified` (bukan draf atau revisi), scope `ReportIssue::reviewableBy()` + `ReportIssuePolicy`.
  Filter jenis, kategori, status tindak lanjut, kampung, periode (+ OPD untuk Admin; `lama=30` dari kartu dasbor).
  Baris membuka detail `/{area}/masalah/{id}`: isi, tautan kelompok & laporan, form "Ubah status tindak lanjut"
  (status, tanggal tindakan, catatan wajib min 5 karakter; Admin memilih OPD penanggung jawab, OPD selalu
  OPD-nya sendiri), dan riwayat. Logika di `App\Actions\Issues\FollowUpIssue` (transaksi + `lockForUpdate`).
  "Unduh Excel" `GET /{area}/masalah/unduh` (filter ikut) → `masalah-kebutuhan-Y-m-d.xlsx` (`Exports\IssueList`).
  Kelompok melihat status + catatan di detail laporan ("Tindak lanjut (n)") dan kartu "Tindak lanjut masalah &
  kebutuhan" di dasbornya; tidak bisa mengubah (403).
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
  lalu diminta revisi tidak terlambat. Status pelaporan per kelompok per periode ada di SATU tempat:
  enum `App\Enums\ReportingState::for($report, $period)` (Sudah terverifikasi / Menunggu verifikasi /
  Perlu revisi / Draf / Belum dibuat / Terlambat) dan versi query-nya `Organization::overdueFor($period)`
  (wajib lapor + lewat tenggat + belum pernah diajukan). Dipakai `ReportingStats::overdue()`, daftar laporan,
  filter `?laporan=terlambat&periode=Y-m` di daftar kelompok, dan notifikasi tenggat. Badge "Terlambat"
  (`fix`) di daftar laporan; kelompok `closed` tidak ditandai terlambat.
- Dasbor Admin & OPD: kartu ringkasan "Terlambat melapor {bulan}" (`shared.dashboards.overdue-stat`) dan
  "Kebutuhan belum ditindaklanjuti" (`shared.dashboards.stale-needs-stat`: kebutuhan berstatus Baru/Ditinjau yang
  tidak berubah > `simker.follow_up.stale_days` (30) hari, dihitung dari `follow_up_at` atau pengajuan pertama,
  `ReportIssue::stale`). Admin "Perlu perhatian" juga memuat "laporan yang hanya bisa diverifikasi Admin".
  Setelah tenggat, daftar "Belum mengajukan" berjudul "Terlambat melapor {bulan}" dengan badge "Terlambat".
  Dasbor Kelompok: "Tenggat {tanggal} · N hari lagi". "Diajukan terlambat" (`wasSubmittedLate()`, informasi saja):
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
- diajukan / diajukan ulang → semua user OPD terkait; bila kelompok tanpa OPD pembina, semua Admin
  (`ReportSubmitted`). Setelah laporan ditinjau, notifikasi ini ditandai dibaca bagi semua penerimanya.
- minta revisi / terverifikasi / dibuka kembali → semua user kelompok tersebut, DAN semua user OPD pembina +
  akun OPD/Admin yang mengisi/mengajukan atas nama kelompok, kecuali peninjau yang bertindak (`ReportReviewed`,
  `audience` reviewer: "Laporan {bulan} {kelompok} diminta revisi oleh {nama} ({instansi}).")
- kemungkinan data ganda (Admin menambah/mengubah kelompok dengan kandidat, atau impor yang memuat kandidat) →
  semua Admin aktif, termasuk yang menyimpan sebagai pengingat (`PossibleDuplicate` lewat `App\Support\DuplicateAlert`;
  impor = satu notifikasi ringkas; tujuan `/admin/kelompok/data-ganda`). Pasangan "Bukan data ganda" tidak memicu.
- profil terverifikasi diubah kelompok (field penting) → akun OPD pembina aktif, atau Admin bila tanpa OPD
  (`ProfileNeedsReview`, tujuan = tab Riwayat kelompok)
- status tindak lanjut masalah/kebutuhan berubah → semua user kelompok aktif, sekali per perubahan; catatan tanpa
  ganti status tidak mengirim notifikasi (`IssueFollowUpUpdated`, tujuan = detail laporan)

### Notifikasi dalam aplikasi
- Data notifikasi menyimpan fakta (`kind`, `monthly_report_id`, nama kelompok, periode, catatan); kalimatnya
  disusun saat tampil oleh `App\Support\NotificationText`. Notifikasi lama yang hanya punya `message`
  ditampilkan apa adanya.
- `/notifikasi` (semua peran): daftar terbaru di atas, "Tandai semua dibaca". `GET /notifikasi/{id}`
  menandai dibaca lalu mengarahkan; tujuan dihitung ulang dari `monthly_report_id` + peran saat ini dan
  dicek Policy `view` (tidak berhak → kembali ke daftar dengan pesan). Notifikasi orang lain → 404.
- Lonceng + angka belum dibaca (maks "9+", warna `brand`) di header HP dan bilah atas tablet/desktop;
  angkanya dari view composer layout `app` (satu query, tanpa polling).
- Notifikasi tenggat (`php artisan simker:pengingat-laporan`), dijadwalkan HARIAN pukul `reports.reminder_time`
  WIT; perintahnya memilih tahap menurut tanggal (tanpa jadwal → tidak mengirim apa pun), untuk periode bulan lalu:
  * "awal" tanggal `reports.reminder_first_day` (1) dan "akhir" H-`reports.reminder_days_before` (3) tenggat
    (`ReportReminder`): ke akun kelompok aktif dari kelompok wajib lapor yang belum pernah mengajukan;
  * "terlambat" H+`reports.overdue_days_after` (1) tenggat (`ReportOverdue`): ke akun kelompok terlambat
    (`overdueFor`) + akun OPD terkait yang aktif; kelompok tanpa OPD → semua Admin aktif.
  Aman dijalankan berulang: tidak dobel per akun, kelompok, periode, dan tahap (akun yang baru dibuat tetap
  menerima sekali). Opsi manual `--tahap=awal|akhir|terlambat --periode=Y-m`.
- Dasbor OPD dan Admin: daftar "Belum mengajukan" punya tombol "Salin pesan pengingat"
  (`App\Support\ReminderMessage`, pola `copyText`) untuk dikirim sendiri, misalnya lewat WhatsApp.

---

## 6. Menu per peran

**Publik:** Beranda · Profil Ransiki · Direktori Kelompok · Produk Unggulan · Berita · Kontak · Masuk

**Kelompok:** Dasbor · Profil Kelompok · Produk · Legalitas & Izin · Laporan Bulanan ·
Notifikasi · Akun Saya

**Admin dan OPD: menu bertab.** Menu yang berkaitan digabung; setiap menu berisi tab, dan setiap tab punya URL sendiri
(bisa dibuka langsung, tombol kembali bekerja). Satu sumber: `App\Support\Navigation::sections()` (menu, tab, badge);
halaman memilih menu + tab lewat `<x-layouts.app section="laporan" tab="verifikasi">`. Layout merender `x-tabs` bertautan
di bawah header (garis bawah `brand` pada tab aktif, angka kecil di tab antrean, geser horizontal di HP), remah bawaan
"Menu › Tab" (mis. "Laporan › Perlu diverifikasi"), dan judul browser "Tab · Menu". Judul halaman = nama tab.

**Admin** (5 menu):
| Menu | Tab (URL) |
|---|---|
| Dasbor | Ringkasan `/admin` · Ekonomi `/admin/dasbor/ekonomi` · Legalitas `/admin/dasbor/legalitas` |
| Kelompok | Semua kelompok `/admin/kelompok` · Menunggu verifikasi profil `/admin/kelompok/verifikasi-profil` · Kemungkinan data ganda `/admin/kelompok/data-ganda` |
| Laporan | Semua laporan `/admin/laporan` · Perlu diverifikasi `/admin/laporan/verifikasi` · Masalah & kebutuhan `/admin/laporan/masalah` |
| Website | Konten Beranda `/admin/website` · Profil Ransiki `/admin/website/profil` · Halaman & kontak `/admin/website/halaman` · Berita & kegiatan `/admin/website/berita` |
| Pengaturan | Pengguna `/admin/pengaturan/pengguna` · Data master `/admin/pengaturan/data-master` (Kampung, Jenis Kelembagaan, Bidang Usaha, OPD) |

**OPD** (3 menu, pola sama, `/opd/...`): Dasbor (Ringkasan · Ekonomi · Legalitas) · Kelompok binaan (Daftar · Menunggu
verifikasi profil) · Laporan (Semua laporan · Perlu diverifikasi · Masalah & kebutuhan).

- Sidebar Admin/OPD hanya berisi menu di atas; Notifikasi (lonceng) dan Akun Saya ada di bilah atas.
- Navigasi bawah HP: Admin = Dasbor · Kelompok · Laporan · Website · Lainnya (Pengaturan, Notifikasi, Akun Saya);
  OPD = Dasbor · Kelompok · Laporan · Notifikasi · Akun (lonceng dan ikon akun di header HP disembunyikan bila sudah
  ada di navigasi bawah).
- Badge (`x-nav-badge`, pil `sun` berteks `ink` 9,0:1, maks "99+", tidak tampil bila 0) di sidebar dan navigasi bawah:
  menu Laporan = laporan menunggu verifikasi, menu Kelompok = profil menunggu verifikasi. Angkanya SAMA dengan isi tab
  antreannya: `MonthlyReport::verificationQueue($user)` (Admin: hanya yang tidak bisa diverifikasi OPD) dan
  `Organization::profileReviewQueue($user)`, dihitung sekali per request (`Navigation::counts`). PENGECUALIAN Bagian 7:
  token `sun` di area aplikasi hanya untuk badge ini (atas permintaan; dijaga `PublicStyleTest`).
- URL lama (`/{area}/statistik`, `/{area}/verifikasi`, `/{area}/verifikasi-profil`, `/{area}/masalah`, `/admin/konten`,
  `/admin/berita`, `/admin/pengguna`, `/admin/data-master`, termasuk sisa path dan query string) dialihkan 301 ke URL
  baru oleh `App\Http\Controllers\LegacyRedirectController` (tanpa middleware; hak akses diperiksa di URL tujuan).
  Nama route TIDAK berubah (mis. `admin.verifications.index`, `admin.statistics.economy`), jadi kode memakai `route()`.
- Halaman Website bukan mode form (tujuan menu utama); bar simpan `x-form-actions sticky` tetap dipakai.

Pakai "Dasbor", bukan "Dashboard", di semua teks antarmuka dan semua ukuran layar.

### Label ringkas (sidebar tablet dan navigasi bawah HP)

| Menu lengkap | Label ringkas |
|---|---|
| Dasbor | Dasbor |
| Profil Kelompok | Profil |
| Produk | Produk |
| Legalitas & Izin | Izin |
| Laporan Bulanan, Laporan | Laporan |
| Notifikasi | Notifikasi |
| Akun Saya | Akun |
| Kelompok, Kelompok binaan | Kelompok |
| Website | Website |
| Pengaturan | Pengaturan |

Nama menu lengkap tetap tampil di sidebar desktop, dan sebagai tooltip saat label ringkas
di-hover atau difokuskan.

---

## 7. Sistem desain

Tujuan: elegan, sederhana, terasa seperti aplikasi, dan TIDAK tampak seperti desain buatan AI.
Rujukan rasa: aplikasi administrasi yang tenang dan rapi, seperti dokumen pemerintah yang
dirancang dengan baik, bukan landing page startup.

### Larangan (penting)
- Tidak ada gradasi, glow, blur, glassmorphism, atau pola latar dekoratif. Pengecualian: overlay foto
  (`.overlay-forest`, lihat di bawah) dan gradasi tipis `canvas` → transparan di tepi kanan tab yang bisa
  digeser di HP (`x-tabs`, hanya saat masih ada tab tersembunyi) sebagai penanda geser.
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
  Pengecualian: jarak setengah langkah (2px/6px, mis. `mt-0.5`, `gap-1.5`) hanya untuk jarak antarbaris
  teks dan ikon-teks di dalam satu kelompok kecil (label + nilai, judul + keterangan, ikon + label).
  Padding baris, jarak label–kontrol, dan jarak antarbagian tetap kelipatan 4px.
- Tinggi kontrol: 40px (desktop), 44px (mobile, agar mudah disentuh). Pengecualian: select di baris
  filter daftar memakai `size="sm"` (36px di desktop, tetap 44px di HP).
- Target sentuh minimal 44px di HP, termasuk tombol ikon (tutup alert/modal), tautan yang berdiri
  sendiri (`x-link`), chip filter, badge/angka yang bisa diklik.
- Lebar konten maksimal 1200px untuk area aplikasi, 1080px untuk halaman publik.

### Tata letak gaya aplikasi (responsif)
- **HP (< 768px):** header atas tipis (judul halaman + cari [Admin/OPD] + lonceng + akun), navigasi bawah tetap
  berisi maksimal 5 menu utama, menu lain di "Lainnya". Tabel berubah menjadi daftar kartu.
  Form satu kolom. Aksi page-header tampil di bawah judul dan ikut tergulir.
  Bar aksi yang menempel di bawah layar hanya untuk halaman form, lewat `<x-form-actions sticky>`.
  Halaman form memakai mode form (`<x-layouts.app focus back="...">`): navigasi bawah dan
  tombol notifikasi disembunyikan, header berisi tombol kembali + judul.
- **Tablet (768–1279px):** sidebar ikon ringkas (lebar 72px) dengan label ringkas 11px
  (lihat Bagian 6) dan tooltip nama menu lengkap saat hover/fokus, konten penuh.
  Tabel tampil dengan kolom penting saja.
- **Desktop (≥ 1280px):** sidebar penuh 248px (logo + nama aplikasi, menu). Tabel lengkap.
- **Bilah atas (tablet dan desktop):** cari global (Admin/OPD; `/{area}/cari`: kelompok menurut nama,
  ketua, kampung; produk menurut nama; dibatasi `visibleTo`), lonceng, menu profil (avatar, nama,
  peran/OPD singkat → Akun Saya, Keluar). Lonceng dan profil tidak lagi di sidebar.
- Halaman publik memakai navigasi atas biasa, dengan menu hamburger di HP dan tablet (< 1024px).
  "Masuk" berupa tautan teks biasa, bukan tombol.
- Website publik bergaya buletin resmi: judul tipografis, bagian dibuka garis `line-strong` + judul
  (`publik._section-heading`), angka besar `tabular-nums`. Tanpa hero bergambar atau kartu fitur.
- Foto produk belum ada → petak inisial (2 huruf) berlatar `subtle`, huruf `ink-muted`. Di beranda,
  produk unggulan berfoto didahulukan. Foto sampul (opsional, dari Konten Website) tampil lebar di
  bawah pembuka beranda dengan keterangan dan nama fotografer.
- Tampilkan "Data diperbarui [tanggal]" di profil kelompok; di beranda "Data per [tanggal]" di bawah angka Tentang.
- Footer publik: identitas + "Dikelola oleh Pemerintah Distrik Ransiki" + tempat logo (`x-logo`),
  tautan cepat, informasi, kontak kantor distrik (daftar OPD ada di halaman Kontak).
- Semua layout punya tautan "Lewati ke konten" ke `#konten`. Area login dan halaman masuk `noindex`.
- Logo memakai komponen `logo` (sementara placeholder "SK"); di tablet hanya logo yang tampil.

### Gaya website publik
Berlaku HANYA untuk halaman publik (`resources/views/publik/`, `components/layouts/public`,
`components/public/`). Area Admin/OPD/Kelompok tetap memakai aturan di atas tanpa pengecualian.

Pengecualian dari Larangan di atas, khusus publik:
- Hero lebar penuh di beranda, HANYA dengan foto slide dari Konten Website (bukan foto stok). Tanpa
  slide aktif: satu foto bawaan dari repo (sementara, sampai foto asli tersedia). Pita judul halaman lain: `forest` polos; Profil Ransiki boleh berfoto.
- Gradasi diizinkan KHUSUS sebagai overlay foto (`.overlay-forest`): desktop forest 85% (0%) → 70%
  (50%) → 20% (100%); HP (< 768px) padat 70%. Teks hero hanya di setengah kiri. Kontras terburuk
  (teks putih di atas bagian foto yang putih) 5,5:1 di titik 50%; gradasi lurus 85%→20% gagal
  (3,3:1) sehingga titik 70% wajib ada. Gradasi lain tetap dilarang.
- Warna tambahan: `forest` #0F2E22 (latar gelap, footer), `forest-soft` #B9C7BF (teks sekunder di
  atas forest, 8,4:1), `forest-line` #2A4A3D (garis di atas forest), `sun` #E9B949 (pita angka,
  tombol utama dan cincin fokus di latar gelap). `ink` di atas `sun` 9,0:1; `sun` di atas `forest`
  8,0:1. DILARANG: teks putih di atas `sun` (1,8:1) dan `sun` sebagai warna teks di latar terang.
  Di atas `sun` semua teks memakai `ink`.
- Label pil (`rounded-full`) untuk jenis, kampung, dan bidang usaha. Kartu publik: radius 8px
  (`rounded-public`), border `line` + bayangan halus `shadow-card` (keduanya khusus publik). Lencana ikon bulat
  (`x-public.icon-badge`) di tepi foto kartu produk/direktori; ikon dari `App\Support\PublicIcons`.
- Judul h1–h3 publik memakai `font-serif`. Label kecil di atas judul bagian (`publik._section-heading`
  `eyebrow`) berwarna `brand`, tanpa garis hiasan. Tidak ada garis emas pendek (di hero, pita, atau label).
- Emas (`sun`) di publik HANYA untuk tombol utama (`x-public.button` sun), aksen kata di judul hero, pil "Pilihan
  distrik" di katalog/detail produk (`bg-sun text-ink`, atas permintaan), dan garis progres membaca di detail berita, plus cincin fokus `on-dark` (fungsi aksesibilitas). Ikon footer, titik slider, pil gelap, dan angka memakai warna netral (dijaga tes).
- Teks tampilan publik dan nilai bawaan `SiteSetting::DEFAULTS` tanpa tanda pisah (– —) dan tanpa kata ulang bertanda
  hubung; tulis dengan koma, titik, atau "sampai" (dijaga tes).
- Judul hero `text-hero` 48px (desktop) / `text-hero-sm` 36px (HP). Hero beranda memakai `.overlay-hero` yang lebih
  tebal (HP 85%; desktop 90% → 85% di 55% → 30%) agar aksen `sun` di judul ≥ 4,5:1 (dijaga `PublicContrastTest`).
  Maks 3 foto dari `hero_slides` aktif (urut posisi; tanpa slide aktif: foto bawaan), slider Alpine `heroSlider`
  (`resources/js/hero-slider.js`): 7 detik, panah + titik, berhenti saat hover/fokus/tab tersembunyi, tanpa gerak
  bila `prefers-reduced-motion`. Foto pertama `fetchpriority="high"`, sisanya lazy.
- Bagian gelap diberi kelas `on-dark` agar cincin fokus berwarna `sun`.

Tetap dilarang di publik: bayangan tebal, emoji, ilustrasi, ikon dekoratif, testimoni, angka
karangan, tombol "Mulai sekarang", teks promosi generik.

Susunan: header putih (logo teks, menu, ikon cari direktori, lalu tombol `sun` "Masuk"/"Dasbor" mulai tablet; di HP
tombol itu ada di menu; menu aktif bergaris bawah `brand`; tanpa bilah atas, kontak kantor ada di footer dan Kontak)
→ (beranda) hero (judul + aksen + kalimat penjelas dari Konten Website) → strip
putih melayang 5 bidang usaha terbanyak (ikon + jumlah, geser horizontal di HP; tingginya `--strip-h` per breakpoint
di `.home-top` (app.css), `absolute bottom-0 translate-y-1/2` sehingga garis bawah hero tepat di tengahnya; ruang
bawah hero dan jarak atas Tentang = `calc(var(--strip-h)/2 + var(--section-y))`, bayangan `shadow-strip`). Semua bagian
beranda memakai satu jarak vertikal `--section-y` (`.home-page`: 4rem HP, 5rem mulai 768px) → "Tentang" (grid 5:7, foto 4:5 `rounded-photo`
12px + `shadow-soft`, konten di tengah vertikal; HP foto 4:3 di atas; judul `about_title` bawaan "Mengenal Distrik
Ransiki", tanpa label kecil; paragraf `ink-soft` maks 30rem ≈ 60 karakter; angka serif `brand` dengan label satu baris
Kelompok/Kampung/Produk unggulan/Bidang usaha, garis atas + pemisah tegak `line`, 2×2 sampai < 1024px; "Data per …";
tautan teks "Baca profil lengkap" + panah) → jenis kelembagaan
(`PublicDirectory::institutionTypes()`) → produk unggulan (berfoto dulu; tanpa foto → foto bidang dari Admin → petak
inisial) → berita & kegiatan (maks 5, disembunyikan bila kosong) → banner ajakan `forest` "Kelompok Anda belum
terdaftar?" → footer `forest` (identitas, Tautan cepat, Informasi, kontak kantor; tanpa newsletter/media sosial).
Halaman lain: pita `forest` dengan remah (`x-public.band crumbs`). Kartu direktori tanpa foto: latar `subtle` +
inisial `ink-muted`. Profil kelompok: pita `forest` polos; foto produk tampil utuh di bagian Produk.

Gambar publik: `<picture>` dengan WebP `srcset` + cadangan JPEG (`x-public.picture`). Foto lebar
(sampul, bidang usaha, "Tentang"): WebP 480/960/1600, hero 1600 ≤ ~200 KB (kualitas turun bertahap
70→50). Foto produk: WebP 400/800 + JPEG 400. Hero `fetchpriority="high"`; gambar lain lazy-load.
Varian dibuat saat unggah; foto lama: `php artisan simker:varian-foto`. Tanpa dukungan WebP di
server, otomatis JPEG saja.

Token `forest`/`sun` tidak boleh dipakai di luar view publik (dijaga tes). Pengecualian: badge menunggu verifikasi
`x-nav-badge` (`sun`, lihat Bagian 6), pita/banner
`x-page-header` (lihat "Pita dan banner area aplikasi") dan halaman masuk (`layouts.auth`): panel kiri forest dengan foto sampul + overlay padat `bg-forest/70`; di atas foto
teks sekunder memakai putih (forest-soft bisa turun ke ~3:1 bila fotonya terang), forest-soft hanya di
atas forest polos.

### Komponen Blade (buat di awal, pakai di semua halaman)
Di `resources/views/components/`:
`button`, `input`, `select`, `textarea`, `checkbox`, `field` (label + input + error + hint),
`card`, `stat` (angka ringkas), `badge` (status), `table` (+ mode kartu di mobile),
`empty-state`, `modal`, `dropdown`, `page-header`, `alert`, `tabs`, `file-upload`,
`form-actions` (bar tombol form; `sticky` hanya untuk halaman form), `checkbox-group`, `logo`,
`number-input` (angka format Indonesia + pratinjau "Terbaca: ..."), `unit-input` (pilihan satuan),
`chart` (kartu grafik Chart.js + "Lihat tabel"; prop `chart` dari `StatisticsCharts`, `panels` untuk beberapa
grafik kecil, `footnote`), `stacked-bar` (bilah bertumpuk berwarna status + legenda berangka yang bisa diklik),
`bar-list` (label + bilah tipis warna `brand` + angka, baris bisa diklik),
`notification-bell` dan `notification-count` (lonceng + angka belum dibaca), `nav-badge` (jumlah menunggu di menu, Bagian 6),
`link` (tautan teks; berdiri sendiri min 44px di HP, `inline` di dalam kalimat bergaris bawah),
`list` + `list.item` (baris daftar yang bisa diklik: min 44px, hover `subtle`, chevron),
`breadcrumbs` (remah, desktop saja), `avatar` (inisial 2 huruf, latar `subtle`, teks `ink-soft`),
`search-input` (ikon kaca pembesar, Enter mencari), `filter-bar` (cari + select ringkas sebaris di
desktop, panel "Filter (n)" di HP), `filter-summary` (ringkasan hasil + chip filter aktif yang bisa
dihapus, `App\Support\FilterChips`), `password-input` (tombol tampilkan kata sandi),
`form-errors` (ringkasan error di atas form panjang + fokus ke isian pertama yang salah),
`stat` (angka ringkas; `icon` + `href` untuk kartu ringkasan), `info-item` (fakta berikon di kartu
informasi), `tag` (label pil di banner detail kelompok),
`layouts.app` (area login), `layouts.public` (website publik), `layouts.auth` (halaman masuk),
`layouts.guest` (halaman error, ganti kata sandi wajib).

Grafik ringkas di dasbor Admin/OPD dibuat dengan CSS/SVG di server. Grafik interaktif (Statistik, dasbor
Kelompok) memakai komponen `chart` (Chart.js, dimuat lewat `import()` dinamis di `Alpine.data('chart')`,
`resources/js/charts.js`): judul, sumber ("Dari laporan terverifikasi."), tombol "Lihat tabel" dengan angka yang
sama (ikut tercetak), `aria-label` yang membacakan isinya, pesan bila belum ada data (bukan grafik kosong).
Tanpa animasi, gradasi, atau bayangan; grid `line`; Public Sans. Warna status hanya untuk segmen status
; grafik lain memakai `brand`, seri kedua `ink-soft`.
Kepatuhan pelaporan (warna khusus grafik ini, di `resources/js/charts.js`, semua ≥ 3:1 terhadap putih), ditumpuk dari
bawah dengan urutan tetap: Terverifikasi `brand`, Menunggu amber #B07A00, Perlu revisi oranye tua #C2410C,
Terlambat merah tua #8E1F17, Belum diajukan abu-abu #7D827F; garis putih 1px antarsegmen; legenda di atas berisi
jumlah bulan terakhir. `sun` tidak dipakai (1,8:1, khusus publik).
Anti-overflow HP: grid yang berisi grafik/tabel memakai `grid-cols-1` (bukan kolom `auto`) dan `min-w-0`;
`x-chart` dan `x-card` sudah `min-w-0`; wadah canvas `relative w-full` bertinggi tetap. Jangan menutupi overflow
dengan `overflow-x: hidden` di body. Tabel berkolom banyak: judul kolom `x-table.heading wrap` (boleh dua baris),
kolom turunan (mis. total) `optional` agar muat di tablet; bila tetap lebih lebar, tabel digeser di dalam kartunya
lewat `x-table label="..."` (region yang bisa difokuskan keyboard).

Halaman tidak boleh menulis ulang gaya tombol/kartu/tautan/baris daftar sendiri. Selalu pakai komponen.

### Header halaman
- Judul + keterangan + aksi di `x-layouts.app`. Badge status di samping judul lewat slot `badge`;
  isi tambahan di bawah keterangan (mis. tag detail kelompok) lewat slot `meta`.
- Dasbor: keterangan kecil "Dasbor" (`eyebrow`) di atas nama (Distrik Ransiki / nama OPD / nama kelompok).
- Halaman bertingkat memakai remah (`breadcrumbs`, desktop) dan tombol kembali di header HP. Detail
  laporan Admin/OPD membawa `?dari=kelompok|antrean|masalah` (divalidasi) agar "Kembali" dan remah
  mengikuti asal halaman, termasuk setelah verifikasi/minta revisi.
- Tombol "Tambah ..." selalu di aksi header; daftar dengan modal (`recordForm`) mendengarkan event
  (`product-create`, `license-create`, `master-create`).
- Badge status kelompok hanya untuk status selain Aktif.
- Halaman error (403/404/419/429/500/503) memakai `layouts.guest` dengan "Kembali" + tujuan utama
  (dasbor bila login); 419 mengajak kembali lalu masuk lagi, 500/503 "Muat ulang".

### Pita dan banner area aplikasi
Pengecualian dari Larangan untuk area Admin/OPD/Kelompok (menyesuaikan referensi tampilan admin):
- Header halaman biasa: pita `forest` polos mulai tablet (di HP tetap putih); halaman form (mode form)
  tetap putih. Banner foto + `.overlay-forest` HANYA di dasbor, detail kelompok, dan Profil Kelompok,
  di semua ukuran. Semua lewat `x-page-header` (prop `band` dari layout, `image` = prop `banner`).
- Di HP (< 768px) banner foto hanya berisi judul + keterangan (lokasi) dengan overlay padat 70%; tag (`meta`)
  dan aksi (Ubah profil, menu "…", pilihan bulan) turun ke baris di bawah banner dengan warna biasa.
- Sumber foto banner (`App\Support\BannerImage`, hanya varian terkompres): dasbor = foto sampul;
  kelompok = HANYA foto kurasi Admin: foto bidang usaha (Konten Website) → foto sampul → pita polos.
  Foto produk/lampiran tidak pernah menjadi latar banner (tetap tampil di kartu Foto dan panel detail).
- Di dalam pita/banner, komponen menyesuaikan warna lewat varian `in-[.app-band]` (mulai tablet) dan
  `in-[.app-band-photo]`: tombol utama putih, sekunder/ghost bergaris/teks putih, tautan putih bergaris
  bawah, remah putih 85%. Cincin fokus `sun`. Token `forest` hanya di `components/page-header` (dijaga tes).
- Ikon dalam lingkaran `brand-tint` (ikon `brand`) HANYA di kartu ringkasan (`x-stat icon`) dan baris
  kartu informasi (`x-info-item`). Ikon kecil `brand` tanpa lingkaran di judul kartu (`x-card icon`) dan
  ikon tab (`x-tabs icons`) HANYA di detail kelompok/Profil Kelompok. Halaman lain tetap tanpa ikon judul.
- Label pil (`x-tag`) untuk jenis dan bidang usaha di banner detail kelompok.

### Daftar dan detail kelompok
- Daftar (Admin/OPD): 4 kartu ringkasan (total kelompok, kampung, bidang usaha, total anggota + "diisi X
  dari Y kelompok") yang mengikuti filter aktif dengan keterangan "sesuai filter"; kartu filter berlabel
  dengan Terapkan/Reset (tidak langsung diterapkan), dua baris di tablet/desktop (cari lebar, jenis, kampung /
  bidang usaha, OPD, status, Terapkan/Reset rata kanan), di HP tanpa kartu: cari sebaris dengan "Filter (n)"; chip filter aktif; tabel + panel detail di kanan
  pada ≥ 1280px (saat panel tampil, kolom Bidang usaha disembunyikan; nama kelompok dan kampung satu baris,
  nama terpotong dengan `title`, jenis di baris kedua). Baris pertama atau `?pilih=id` dirender di server; klik baris memuat
  `/{area}/kelompok/{id}/ringkas` (Policy `view`). Layar lebih kecil membuka halaman detail.
- Detail: remah → banner (status bila tidak aktif, nama, lokasi, tag, Ubah profil, menu "…" Admin untuk
  ubah status dengan alasan/hapus) → tab berikon → tab Profil: Ringkasan, Informasi umum, Lokasi (tombol "Buka di
  Google Maps" dari pencarian alamat, tanpa peta tertanam), Deskripsi, Foto (foto produk lalu lampiran
  gambar), Publikasi & pelaporan, Dokumen. Tidak menambah kolom baru tanpa persetujuan.

### Tabel dan kartu daftar
- Tidak ada tombol "Buka" per baris. Baris yang punya halaman detail bisa diklik seluruhnya:
  `<x-table clickable>` + `<x-table.row href="...">`. Link ada di sel `primary` (`data-row-link`, lapisan
  klik hanya sebesar sel itu); klik di bagian lain baris (`tr[data-row-href]`) diteruskan ke link itu oleh
  `openRowLink` di `resources/js/app.js` (Ctrl/Cmd/klik tengah = tab baru). JANGAN memperluas lapisan klik
  sampai `<tr>`: Safari tidak menjadikan `<tr>` berposisi relative sebagai acuan, sehingga baris tidak bisa
  diklik. Tetap satu link yang bisa difokuskan dengan keyboard (cincin fokus mengelilingi baris lewat `has-[]`). Hover memakai latar `subtle`, chevron-right kecil `ink-muted` di kanan.
- Tombol aksi lain di dalam baris dihindari; bila terpaksa, beri `relative z-10` agar bisa diklik.
- Pengecualian: daftar tanpa halaman detail (mis. Data Master) memakai sel `actions` berisi
  tombol Ubah/Hapus, dengan tambah/ubah lewat modal (`recordForm` di `resources/js/app.js`).
- Mode kartu di HP: baris atas berisi nama (`primary`) dan badge status (`badge`) sejajar,
  subjudul (`subtitle`, mis. periode) `ink-soft` di bawah nama, sel lain menjadi baris
  label-nilai. Seluruh kartu bisa diketuk, chevron di kanan.

### Form
- Setiap form selain GET mencegah kirim ganda (resources/js/app.js): tombol kirim dinonaktifkan,
  `aria-busy`, label diganti teks `processing` pada `x-button` (bawaan "Memproses…", mis. "Menyimpan…",
  "Menghapus…"). Kecualikan dengan `data-no-processing`.
- Form panjang menampilkan `x-form-errors` di atas form. Form Alpine memakai `invalid-if` pada
  input/select/textarea, bukan menyalin kelas error.
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
12. Uji menyeluruh per peran, perbaikan tampilan HP/tablet, `npm run build`, persiapan deploy
    (12A persiapan produksi selesai: seeder produksi, create-admin, backup, header keamanan, `docs/DEPLOY.md`).

**Diupayakan setelah inti selesai** (urutan prioritas):
~~status organisasi di UI~~ (selesai, Paket 1) · ~~pemisahan penginput & pemverifikasi~~ (selesai, Paket 4) ·
~~Berita & Kegiatan~~ (selesai, Paket 5) · ~~peringatan nama ganda~~ (selesai, Paket 4) · ~~batas waktu & status terlambat~~ (selesai, Paket 1) ·
~~beberapa akun per kelompok~~ (selesai, Paket 1) · ~~verifikasi profil + riwayat perubahan~~ (selesai, Paket 5) ·
~~grafik ekonomi~~ · ~~dasbor legalitas~~ (selesai, Paket 3) · ~~tindak lanjut intervensi~~ (selesai, Paket 4) · ~~email undangan/reset~~ (selesai, Paket 5) ·
~~ekspor Excel/PDF & impor spreadsheet~~ (selesai, Paket 2).

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
- Riwayat laporan terverifikasi Januari–Juni 2026 (sebagian bulan sengaja kosong) untuk grafik, lalu
  laporan bulanan 3 bulan terakhir dengan status beragam (draft, submitted, revision, verified),
  sebagian kelompok sengaja belum melapor, sebagian `no_activity`, sebagian angka keuangan kosong.
- Akun uji (kata sandi `password`, hanya untuk local):
  `admin` · `opd.pertanian` · `opd.dpmptsp` · `opd.dinkopdag` · `opd.perikanan` ·
  `kelompok1`, `kelompok2` (dua kelompok berbeda, untuk uji akses silang)

---

## 11. Checklist sebelum menyatakan fitur selesai

- [ ] Halaman terbuka tanpa error di lebar 375px, 820px, dan 1440px
- [ ] Cek manual di lebar 360px, 390px, 768px, dan 1280px: tidak ada geser horizontal,
      grafik dan tabel tetap di dalam kartunya.
- [ ] Hanya memakai komponen dan token desain yang ada
- [ ] Policy/scope memblokir akses peran lain (ada feature test)
- [ ] Validasi form dan pesan error berbahasa Indonesia
- [ ] Keadaan kosong (empty state) ditangani
- [ ] Tidak ada N+1 query yang jelas
- [ ] `sail artisan test` lulus

---

## 12. Deploy ke Hostinger (catatan)

Panduan langkah demi langkah: `docs/DEPLOY.md` (perbarui bila langkah deploy berubah).

- Data awal server: `db:seed --class=ProductionSeeder --force` (data master saja; `OPDS`/`VILLAGES` di seeder
  kosong sampai daftar resmi diterima), lalu `php artisan simker:create-admin` (interaktif, kata sandi tersembunyi,
  tanpa wajib ganti). `DatabaseSeeder` = ProductionSeeder (+ DemoSeeder hanya di local). `DemoSeeder` melempar
  `RuntimeException` di luar local/testing.
- Keamanan respons: middleware global `App\Http\Middleware\SecureResponse` (X-Frame-Options SAMEORIGIN, nosniff,
  Referrer-Policy, Permissions-Policy; header yang sudah diisi controller tidak ditimpa). Di produksi: HTTP → HTTPS
  (301, atau 308 untuk POST) + HSTS + `URL::forceScheme('https')`, bisa dimatikan sementara `SIMKER_FORCE_HTTPS=false`.
  Di belakang CDN isi `TRUSTED_PROXIES=*` (`config/trustedproxy.php`). Cookie session `secure` bawaan true di produksi.
- Cadangan: `simker:backup-db` (mysqldump → gzip, `storage/app/private/backups/simker-*.sql.gz`, simpan
  `simker.backup.keep` = 14), harian 02.00 WIT. Kata sandi DB lewat file opsi sementara (600), bukan argumen.
- Favicon: `public/favicon.svg` (sumber), `favicon.ico` (16/32/48) dan `apple-touch-icon.png` (180, tanpa sudut
  membulat) dirender dari SVG itu; tautannya di `partials/head`. Buat ulang bila logo resmi tersedia.
- Anggaran query halaman daftar/dasbor/publik dijaga `tests/Feature/Production/QueryBudgetTest.php` (< 40 query
  dengan data contoh).
- Jangan mengunggah `public/hot` ke server (halaman akan mencari server Vite lokal).

- Butuh paket dengan akses SSH dan Composer (Business ke atas) atau VPS. PHP 8.3+.
- Arahkan document root ke folder `public` (atau simlink `public_html` → `public`).
- `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`,
  `php artisan config:cache route:cache view:cache`.
- Aset dibangun di lokal (`npm run build`) lalu folder `public/build` ikut diunggah;
  Node tidak wajib ada di server.
- File privat tetap di `storage/app/private`, tidak bisa diakses langsung dari URL.
- PHP: ekstensi `gd` (dengan dukungan WebP; tanpa itu gambar publik otomatis JPEG saja), `exif`, `fileinfo` wajib aktif.
  Untuk ekspor/impor (Paket 2) juga wajib: `zip`, `xmlreader`, `xmlwriter`, `dom`, `libxml`, `filter` (Excel/openspout),
  `mbstring` (PDF/dompdf). Folder `storage/fonts` harus bisa ditulis (cache huruf dompdf). `upload_max_filesize` dan `post_max_size`
  minimal 12M, `memory_limit` minimal 512M (gambar besar didekode penuh di memori sebelum diperkecil).
- HTTPS wajib: tombol "Salin info masuk" memakai clipboard browser yang hanya penuh berfungsi di HTTPS.
- `/sitemap.xml` dan `/robots.txt` dibuat dinamis (tidak ada file `public/robots.txt`). Gambar pratinjau
  tautan bawaan: `public/og-default.png` (1200×630). Pastikan `APP_URL` benar agar URL Open Graph absolut.
- Cron WAJIB untuk notifikasi tenggat laporan (dicek setiap hari 08.00 WIT, lihat `routes/console.php`):
  `* * * * * php /path/artisan schedule:run >> /dev/null 2>&1` (Cron Jobs di hPanel Hostinger).
  Tanpa cron ini pengingat tidak pernah terkirim. Untuk uji lokal, jalankan perintahnya manual:
  `sail artisan simker:pengingat-laporan --tahap=terlambat` (atau `awal`/`akhir`, opsi `--periode=2026-09`); cek jadwal dengan
  `sail artisan schedule:list`.
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Jayapura`.
- Email (undangan akun + lupa kata sandi) lewat SMTP email Hostinger. Buat kotak surat di hPanel (Emails →
  mis. `noreply@domain-anda`), lalu isi `.env` (JANGAN menyimpan kata sandi asli di repo atau CLAUDE.md):
  ```
  MAIL_MAILER=smtp
  MAIL_SCHEME=smtps
  MAIL_HOST=smtp.hostinger.com
  MAIL_PORT=465
  MAIL_USERNAME=noreply@domain-anda
  MAIL_PASSWORD=<kata sandi kotak surat>
  MAIL_FROM_ADDRESS=noreply@domain-anda
  MAIL_FROM_NAME="SIM-KER Distrik Ransiki"
  QUEUE_CONNECTION=sync
  ```
  Port 587 juga bisa (`MAIL_SCHEME=smtp`, STARTTLS). Alamat pengirim harus sama dengan kotak surat yang login.
  Setelah mengubah `.env`, jalankan ulang `php artisan config:cache`. Uji dengan "Lupa kata sandi?" ke email sendiri.
  `APP_URL` wajib benar (https) karena tautan di email dibuat dari situ.
- Backup database berkala (fitur backup Hostinger + ekspor manual sebelum update besar).
