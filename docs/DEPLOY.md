# Deploy SIM-KER ke Hostinger

Panduan langkah demi langkah memasang SIM-KER Distrik Ransiki di Hostinger (paket dengan akses SSH dan
Composer: Business ke atas, Cloud, atau VPS). Contoh perintah memakai domain `simker.contoh.id` dan
pengguna SSH `u123456789`; ganti dengan milik Anda.

Ringkasan: kode di `~/domains/<domain>/simker`, folder `public_html` diarahkan ke `simker/public`, aset
dibangun di laptop, database MySQL dari hPanel, cron `schedule:run` setiap menit.

---

## 1. Persiapan di hPanel

1. **PHP**: Websites → Advanced → PHP Configuration → pilih PHP **8.3** atau lebih baru.
   - Ekstensi wajib aktif: `gd` (dengan WebP), `exif`, `fileinfo`, `zip`, `xmlreader`, `xmlwriter`, `dom`,
     `libxml`, `filter`, `mbstring`, `pdo_mysql`, `openssl`, `intl` (umumnya sudah aktif).
   - PHP Options: `upload_max_filesize` ≥ `12M`, `post_max_size` ≥ `12M`, `memory_limit` ≥ `512M`,
     `max_execution_time` ≥ `120`. Semua unggahan foto dibatasi 10 MB per file; 12M memberi ruang untuk data form
     lain di permintaan yang sama. Di bawah itu, foto besar gagal dengan "File terlalu besar" sebelum sampai ke aplikasi.
   - Opsional: ekstensi `imagick` dengan dukungan HEIC (libheif + dekoder HEVC) agar foto iPhone (HEIC) yang terkirim
     tanpa JavaScript tetap bisa dikonversi di server. Tanpa itu tidak apa-apa: HEIC sudah diubah ke JPG di browser,
     dan yang lolos ditolak dengan pesan cara mengirim ulang. Matikan paksa dengan `SIMKER_CONVERT_HEIC=false`.
2. **Database**: Databases → MySQL Databases → buat database + pengguna. Catat nama database, nama
   pengguna, dan kata sandinya (bentuknya `u123456789_simker`).
3. **SSH**: Advanced → SSH Access → aktifkan, catat host, port (biasanya `65002`), dan pengguna.
4. **SSL**: Security → SSL → pasang sertifikat gratis (Let's Encrypt) untuk domain. Tunggu sampai
   statusnya aktif sebelum membuka aplikasi: aplikasi memaksa HTTPS di produksi.
5. **Email** (undangan akun dan "Lupa kata sandi?"): Emails → buat kotak surat, mis.
   `noreply@simker.contoh.id`. Catat kata sandinya.

## 2. Siapkan rilis di laptop

```bash
git checkout main && git pull
sail npm ci
sail npm run build          # menghasilkan public/build (tidak ikut git)
sail artisan test           # pastikan semua tes lulus
```

`public/build` tidak tersimpan di git, jadi harus diunggah terpisah (langkah 3). **Jangan** mengunggah
`public/hot`: file itu membuat halaman mencari server Vite lokal dan tampil tanpa gaya. Folder
`node_modules`, `vendor`, `.env`, dan `storage/app/private/*` juga tidak diunggah.

## 3. Unggah kode

Masuk lewat SSH:

```bash
ssh -p 65002 u123456789@<host-ssh>
cd ~/domains/simker.contoh.id
```

**Cara A, git (disarankan):**

```bash
git clone https://github.com/mohbatara/simker-ransiki.git simker
```

Repo privat: buat deploy key (`ssh-keygen -t ed25519`, tempel isi `~/.ssh/id_ed25519.pub` di GitHub →
Settings → Deploy keys), lalu clone dengan alamat `git@github.com:...`.

Lalu dari laptop, unggah aset hasil build:

```bash
rsync -az -e "ssh -p 65002" public/build/ u123456789@<host-ssh>:domains/simker.contoh.id/simker/public/build/
```

**Cara B, arsip zip:** buat zip proyek tanpa `node_modules`, `vendor`, `.env`, `public/hot`, dan
`storage/app/private/*` (tetapi DENGAN `public/build`), unggah lewat File Manager ke
`domains/simker.contoh.id/`, lalu ekstrak menjadi folder `simker`.

## 4. Pasang dependensi

```bash
cd ~/domains/simker.contoh.id/simker
composer install --no-dev --optimize-autoloader
```

Bila perintah `composer` tidak dikenal, coba `composer2`, atau
`php ~/composer.phar` setelah mengunduh Composer.

## 5. Isi `.env` produksi

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Variabel yang wajib diubah (nilai rahasia JANGAN disimpan di repo, CLAUDE.md, atau chat):

| Variabel | Isi |
|---|---|
| `APP_NAME` | `"SIM-KER Ransiki"` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (detail error tidak pernah tampil ke pengunjung) |
| `APP_URL` | `https://simker.contoh.id` (https, tanpa garis miring di akhir; dipakai tautan email, sitemap, Open Graph) |
| `APP_TIMEZONE` | `Asia/Jayapura` |
| `APP_KEY` | diisi otomatis oleh `key:generate`; simpan salinannya di tempat aman |
| `LOG_LEVEL` | `warning` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `localhost` (atau host dari hPanel) |
| `DB_PORT` | `3306` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | dari langkah 1.2 |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `240` |
| `SESSION_EXPIRE_ON_CLOSE` | `true` |
| `SESSION_SECURE_COOKIE` | `true` (bawaan di produksi; cookie hanya lewat HTTPS) |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `FILESYSTEM_DISK` | `local` |
| `MAIL_MAILER` | `smtp` |
| `MAIL_SCHEME` | `smtps` |
| `MAIL_HOST` | `smtp.hostinger.com` |
| `MAIL_PORT` | `465` |
| `MAIL_USERNAME`, `MAIL_FROM_ADDRESS` | alamat kotak surat dari langkah 1.5 (keduanya sama) |
| `MAIL_PASSWORD` | kata sandi kotak surat |
| `MAIL_FROM_NAME` | `"SIM-KER Distrik Ransiki"` |

Opsional:

| Variabel | Kapan diisi |
|---|---|
| `TRUSTED_PROXIES` | `*` bila memakai CDN Hostinger atau Cloudflare. Tanpa ini, situs di belakang CDN bisa berputar-putar dialihkan ke HTTPS. |
| `SIMKER_FORCE_HTTPS` | `false` hanya sementara bila SSL belum aktif. Kembalikan ke `true`. |
| `SIMKER_MYSQLDUMP` | path lengkap mysqldump bila cadangan dari cron gagal "tidak ditemukan" (lihat `which mysqldump`). |
| `SIMKER_BACKUP_KEEP` | jumlah cadangan yang disimpan (bawaan 14). |

Lindungi file `.env`: `chmod 600 .env`.

## 6. Arahkan document root ke `public`

Hostinger melayani `~/domains/simker.contoh.id/public_html`. Ganti folder itu dengan tautan ke
`simker/public`:

```bash
cd ~/domains/simker.contoh.id
mv public_html public_html.lama     # simpan dulu isi bawaan Hostinger
ln -s simker/public public_html
```

Dengan begitu `.env`, `storage`, dan kode tidak bisa dibuka dari browser. File unggahan (lampiran, foto
produk, foto berita) disimpan di `storage/app/private` dan hanya diunduh lewat route yang memeriksa hak
akses, sehingga **`php artisan storage:link` tidak dipakai** dan tidak perlu dijalankan.

## 7. Database, data awal, dan Admin pertama

```bash
cd ~/domains/simker.contoh.id/simker
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
php artisan simker:create-admin
```

- `ProductionSeeder` hanya mengisi data master (jenis kelembagaan, bidang usaha; OPD dan kampung bila
  daftar resminya sudah dimasukkan ke seeder). Aman dijalankan ulang.
- `DemoSeeder` (data contoh + akun uji berkata sandi `password`) **ditolak** di produksi.
- `simker:create-admin` menanyakan nama, username, email (boleh kosong), dan kata sandi (tersembunyi,
  minimal 8 karakter). Setelah itu masuk di `https://simker.contoh.id/login`, lalu lengkapi Data Master
  (kampung, OPD), Konten Website, dan akun pengguna lain dari menu Admin.

## 8. Izin folder dan cache

```bash
chmod -R 775 storage bootstrap/cache
php artisan optimize          # cache config, route, view, event
```

Setiap kali `.env` diubah, jalankan `php artisan optimize` lagi (nilai `.env` dibaca dari cache).

## 9. Cron (wajib)

hPanel → Advanced → Cron Jobs → tambah cron **setiap menit**:

```
* * * * * cd /home/u123456789/domains/simker.contoh.id/simker && php artisan schedule:run >> /dev/null 2>&1
```

Cron ini menjalankan:
- `simker:pengingat-laporan` setiap hari 08.00 WIT (pengingat dan notifikasi terlambat laporan);
- `simker:backup-db` setiap hari 02.00 WIT (cadangan database).

Tanpa cron, pengingat dan cadangan tidak pernah berjalan. Cek jadwal dengan
`php artisan schedule:list`. Bila `php` di cron bukan PHP 8.3, pakai path lengkapnya
(mis. `/opt/alt/php83/usr/bin/php`; lihat `which php` di SSH).

## 10. Periksa setelah pasang

- [ ] `https://simker.contoh.id` terbuka, `http://` dialihkan ke `https://`.
- [ ] Halaman yang tidak ada menampilkan "Halaman tidak ditemukan" (bukan detail error Laravel).
- [ ] Masuk sebagai Admin berhasil.
- [ ] "Lupa kata sandi?" ke email sendiri: email masuk dan tautannya `https://`.
- [ ] Unggah satu foto dan satu PDF di profil kelompok, lalu buka lagi.
- [ ] `php artisan simker:backup-db` berhasil dan file muncul di `storage/app/private/backups`.
- [ ] `https://simker.contoh.id/robots.txt` dan `/sitemap.xml` terbuka.

---

## Memperbarui ke versi berikutnya

Di laptop: tes lulus, `sail npm run build`. Lalu di server:

```bash
cd ~/domains/simker.contoh.id/simker
php artisan simker:backup-db               # cadangan sebelum update
php artisan down                           # halaman "Sedang diperbarui"
git pull                                   # atau unggah file baru (Cara B)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force   # bila data master bertambah
php artisan optimize
php artisan up
```

Dan dari laptop, unggah ulang `public/build` (perintah `rsync` di langkah 3) **sebelum** `php artisan up`.
Bila rilis membawa foto publik lama tanpa varian WebP: `php artisan simker:varian-foto`.

## Cadangan

**Otomatis:** `simker:backup-db` menulis `storage/app/private/backups/simker-YYYY-MM-DD-HHMMSS.sql.gz`
setiap hari pukul 02.00 WIT dan menyimpan 14 file terakhir. Kata sandi database tidak muncul di daftar
proses (ditulis ke file opsi sementara berizin 600).

Cadangan itu masih di server yang sama. Unduh salinan berkala ke tempat lain, mis. seminggu sekali:

```bash
rsync -az -e "ssh -p 65002" u123456789@<host-ssh>:domains/simker.contoh.id/simker/storage/app/private/backups/ ./cadangan-simker/
```

**File unggahan** (lampiran, foto) tidak termasuk dalam cadangan database. Cadangkan foldernya:

```bash
tar -czf ~/simker-file-$(date +%F).tar.gz -C ~/domains/simker.contoh.id/simker/storage/app private --exclude=private/backups
```

**Bila mysqldump tidak tersedia** (`simker:backup-db` gagal dengan "tidak ditemukan"):
1. Cari path-nya: `which mysqldump` atau `ls /usr/bin/mysqldump /usr/local/bin/mysqldump`, lalu isi
   `SIMKER_MYSQLDUMP` di `.env` dan jalankan `php artisan optimize`.
2. Bila benar-benar tidak ada: pakai cadangan otomatis Hostinger (hPanel → Files → Backups; harian di
   paket Business ke atas, bisa diunduh per database) dan ekspor manual lewat phpMyAdmin (Databases →
   phpMyAdmin → Export → Quick → SQL) sebelum setiap update besar.

## Memulihkan cadangan

Memulihkan menimpa seluruh isi database. Lakukan hanya bila perlu, dan cadangkan kondisi sekarang dulu.

```bash
cd ~/domains/simker.contoh.id/simker
php artisan simker:backup-db                     # simpan kondisi sekarang
php artisan down
ls storage/app/private/backups                   # pilih file yang akan dipulihkan
gunzip -c storage/app/private/backups/simker-2026-10-11-020000.sql.gz \
  | mysql -h localhost -u u123456789_simker -p u123456789_simker
php artisan migrate --force                      # bila cadangan lebih lama dari versi aplikasi
php artisan optimize:clear && php artisan optimize
php artisan up
```

`mysql -p` menanyakan kata sandi database (dari `.env`). Tanpa SSH: phpMyAdmin → pilih database →
Import → unggah file `.sql.gz`.

Memulihkan file unggahan: `tar -xzf simker-file-YYYY-MM-DD.tar.gz -C ~/domains/simker.contoh.id/simker/storage/app`.

## Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| Halaman putih tanpa gaya | `public/build` belum diunggah atau `public/hot` ikut terunggah (hapus). |
| "Too many redirects" | Situs di belakang CDN: isi `TRUSTED_PROXIES=*`, lalu `php artisan optimize`. |
| Error 500 setelah ubah `.env` | Jalankan `php artisan optimize`. Lihat `storage/logs/laravel.log`. |
| Email tidak terkirim | Cek `MAIL_*`, alamat pengirim harus sama dengan kotak surat yang login. |
| 419 "Sesi berakhir" terus-menerus | `APP_URL` harus `https://` dan sama dengan domain yang dibuka. |
| Unggah foto gagal "terlalu besar" | Naikkan `upload_max_filesize`/`post_max_size` di PHP Options. |
