# Daftar foto sementara untuk presentasi

Foto di folder ini hanya untuk demo (boleh dari internet) dan **tidak ikut ke GitHub** (lihat `.gitignore`).
Hanya file ini dan struktur folder yang tersimpan di repo.

Cara memakai:

1. Simpan foto dengan nama file persis seperti kolom "Nama file" di bawah, di subfolder yang sesuai.
   Ekstensi boleh `.jpg`, `.jpeg`, `.png`, atau `.webp`.
2. Jalankan `sail artisan migrate:fresh --seed`. Foto dikecilkan otomatis (maksimal 1600 px).
3. Urutan pemakaian oleh seeder: foto di folder ini, lalu foto klien yang cocok (`media/produk`, `media/kelompok`,
   `media/berita`), lalu inisial bila tidak ada keduanya.

Saran ukuran: foto lanskap untuk berita dan kelompok (mis. 1600 × 1067), foto persegi untuk produk (mis. 1200 × 1200).
Hindari foto berwatermark, foto berisi wajah yang jelas dikenali, dan logo merek lain.

Daftar diurutkan dari yang paling sering terlihat saat demo. Kolom "Sekarang" menunjukkan foto yang dipakai bila
folder ini kosong.

## Produk (`produk/`)

Tampil di Beranda (Produk unggulan), halaman Produk, dan kartu Direktori (foto produk pertama kelompok).

| No | Produk | Kelompok | Nama file | Saran isi foto | Sekarang |
|---|---|---|---|---|---|
| 1 | Kakao fermentasi kering | Kelompok Tani Kakao Harapan | `kakao-fermentasi-kering.jpg` | biji kakao kering di tampah atau karung goni | inisial |
| 2 | Keripik pisang | KUB Sumber Berkat | `keripik-pisang.jpg` | kemasan keripik pisang berlabel | inisial |
| 3 | Minyak kelapa murni | UMKM Minyak Kelapa Murni Pantai Damai | `minyak-kelapa-murni.jpg` | botol minyak kelapa murni bening | inisial |
| 4 | Kopi bubuk | Kelompok Tani Kopi Pegunungan Sungai Bening | `kopi-bubuk.jpg` | kemasan kopi bubuk dan biji kopi sangrai | inisial |
| 5 | Ikan asap | Kelompok Nelayan Tanjung Pasir Bersatu | `ikan-asap.jpg` | ikan asap di para-para atau tungku | inisial |
| 6 | Cabai rawit | Kelompok Tani Sayur Lembah Subur | `cabai-rawit.jpg` | cabai rawit segar di keranjang | inisial |
| 7 | Beras lokal | BUMDes Maju Bersama | `beras-lokal.jpg` | karung beras di gudang | inisial |
| 8 | Beras | KDMP Bukit Hijau | `beras.jpg` | beras kemasan di rak toko koperasi | inisial |
| 9 | Sagu lempeng | UMKM Sagu Olahan Mama Muara | `sagu-lempeng.jpg` | sagu lempeng tersusun | inisial |
| 10 | Kerupuk ikan | KUB Abon Ikan Mina Sejahtera | `kerupuk-ikan.jpg` | kemasan kerupuk ikan | inisial |
| 11 | Telur ayam kampung | Kelompok Peternak Ayam Kampung Subur Makmur | `telur-ayam-kampung.jpg` | telur ayam kampung di rak telur | inisial |
| 12 | Abon ikan tongkol | KUB Abon Ikan Mina Sejahtera | `abon-ikan-tongkol.jpg` | kemasan abon ikan | foto klien (abon ikan) |
| 13 | Ayam kampung | Kelompok Peternak Ayam Kampung Subur Makmur | `ayam-kampung.jpg` | ayam kampung di halaman kandang | foto klien (ayam kampung) |
| 14 | Noken kulit kayu | Kelompok Perajin Noken Mama Sejahtera | `noken-kulit-kayu.jpg` | noken kulit kayu | foto klien (tas anyaman) |
| 15 | Tas dan topi anyaman | Kelompok Perajin Noken Mama Sejahtera | `tas-dan-topi-anyaman.jpg` | tas dan topi anyaman | foto klien (tas dan topi anyaman) |

## Berita (`berita/`)

Tampil di Beranda (Berita dan kegiatan) dan halaman Berita.

| No | Berita | Nama file | Saran isi foto | Sekarang |
|---|---|---|---|---|
| 1 | Batas pengiriman laporan bulanan setiap tanggal 10 | `batas-pengiriman-laporan-bulanan-setiap-tanggal-10.jpg` | petugas dan ketua kelompok mengisi laporan di kantor distrik | tanpa foto |
| 2 | Pelatihan pengasapan ikan untuk kelompok nelayan | `pelatihan-pengasapan-ikan-untuk-kelompok-nelayan.jpg` | pelatihan pengasapan ikan bersama nelayan | foto klien (petugas bersama nelayan) |
| 3 | Produk olahan kelompok dijual bersama di Pasar Ransiki | `produk-olahan-kelompok-dijual-bersama-di-pasar-ransiki.jpg` | lapak produk olahan di pasar | foto klien (Pasar Ransiki) |
| 4 | Petugas meninjau kandang kelompok peternak ayam kampung | `petugas-meninjau-kandang-kelompok-peternak-ayam-kampung.jpg` | petugas di kandang ayam | foto klien (kandang ayam) |

## Kelompok (`kelompok/`)

Disimpan sebagai lampiran gambar kelompok: tampil di kartu Foto pada detail kelompok (Admin/OPD) dan Profil kelompok
(akun kelompok), sesudah foto produk. Belum tampil di Direktori publik karena belum ada kolom foto profil kelompok.

| No | Kelompok | Nama file | Saran isi foto | Sekarang |
|---|---|---|---|---|
| 1 | Kelompok Tani Kakao Harapan (akun Yakob Rumbiak) | `kelompok-tani-kakao-harapan.jpg` | anggota di kebun kakao | tanpa foto |
| 2 | Kelompok Peternak Ayam Kampung Subur Makmur | `kelompok-peternak-ayam-kampung-subur-makmur.jpg` | kandang ayam kelompok | foto klien (kandang ayam) |
| 3 | Kelompok Tani Sayur Lembah Subur | `kelompok-tani-sayur-lembah-subur.jpg` | lahan sayur dan bedengan | tanpa foto |
| 4 | KUB Abon Ikan Mina Sejahtera | `kub-abon-ikan-mina-sejahtera.jpg` | dapur produksi abon ikan | tanpa foto |
| 5 | BUMDes Maju Bersama | `bumdes-maju-bersama.jpg` | kios atau gudang BUMDes | tanpa foto |
| 6 | Kelompok Nelayan Tanjung Pasir Bersatu (akun kelompok2) | `kelompok-nelayan-tanjung-pasir-bersatu.jpg` | perahu nelayan di pantai | foto klien (perahu nelayan) |
| 7 | KUB Sumber Berkat | `kub-sumber-berkat.jpg` | anggota menggoreng atau mengemas keripik | tanpa foto |
| 8 | Kelompok Perajin Noken Mama Sejahtera | `kelompok-perajin-noken-mama-sejahtera.jpg` | mama-mama merajut noken | tanpa foto |
| 9 | Kelompok Tani Kopi Pegunungan Sungai Bening | `kelompok-tani-kopi-pegunungan-sungai-bening.jpg` | kebun kopi di lereng | tanpa foto |
| 10 | UMKM Minyak Kelapa Murni Pantai Damai | `umkm-minyak-kelapa-murni-pantai-damai.jpg` | pengolahan kelapa | tanpa foto |
| 11 | KDMP Bukit Hijau | `kdmp-bukit-hijau.jpg` | toko koperasi | tanpa foto |
| 12 | UMKM Sagu Olahan Mama Muara | `umkm-sagu-olahan-mama-muara.jpg` | pengolahan sagu | tanpa foto |
