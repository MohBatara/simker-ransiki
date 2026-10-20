<?php

namespace Database\Seeders;

/**
 * Katalog data demo untuk DemoSeeder. Semua nama orang, kelompok, nomor telepon, nomor izin, dan email
 * fiktif. Nama tokoh/pejabat nyata dan nama kampung asli sengaja tidak dipakai.
 */
final class DemoData
{
    /**
     * Nama samaran yang netral. Ganti dengan daftar kampung resmi dari klien (lewat ProductionSeeder::VILLAGES).
     */
    public const VILLAGES = [
        'Kampung Bukit Hijau',
        'Kampung Sumber Air',
        'Kampung Tanjung Pasir',
        'Kampung Muara Indah',
        'Kampung Mekar Jaya',
        'Kampung Lembah Subur',
        'Kampung Pantai Damai',
        'Kampung Sungai Bening',
    ];

    /** short_name => nama OPD (sesuai blueprint klien; akan diganti nama resmi). */
    public const OPDS = [
        'Pertanian' => 'Dinas Pertanian dan Perkebunan',
        'DPMPTSP' => 'DPMPTSP',
        'Dinkopdag' => 'Dinkopdag',
        'Perikanan' => 'Dinas Perikanan',
    ];

    /** short_name OPD => nama petugas (fiktif). */
    public const OPD_OFFICERS = [
        'Pertanian' => 'Yosias Kafiar',
        'DPMPTSP' => 'Rina Kusumawati',
        'Dinkopdag' => 'Herman Saroy',
        'Perikanan' => 'Agustina Wairara',
    ];

    /**
     * Pengali musiman per bulan (Januari = indeks 0). Perikanan turun saat angin barat (Desember–Februari),
     * kakao dan kopi memuncak pertengahan tahun, olahan dan kerajinan naik menjelang Agustus dan Desember.
     */
    public const SEASONS = [
        'kakao' => [0.7, 0.6, 0.7, 0.9, 1.2, 1.4, 1.3, 1.1, 0.8, 0.8, 1.0, 0.9],
        'kopi' => [0.5, 0.4, 0.5, 0.8, 1.3, 1.6, 1.5, 1.3, 0.9, 0.6, 0.5, 0.5],
        'pinang' => [0.9, 0.9, 1.0, 1.1, 1.1, 1.0, 1.0, 1.1, 1.0, 1.0, 0.9, 0.9],
        'sagu' => [1.0, 1.0, 1.0, 0.9, 1.0, 1.1, 1.0, 1.0, 1.1, 1.0, 1.0, 0.9],
        'ikan' => [0.5, 0.6, 0.8, 1.0, 1.1, 1.0, 0.9, 1.0, 1.2, 1.3, 1.2, 0.7],
        'kepiting' => [0.8, 0.8, 0.9, 1.0, 1.1, 1.1, 1.0, 1.0, 1.1, 1.1, 1.0, 0.9],
        'olahan' => [0.9, 0.8, 0.9, 1.0, 1.0, 1.0, 1.0, 1.2, 1.0, 1.0, 1.1, 1.4],
        'kelapa' => [1.0, 0.9, 1.0, 1.0, 1.1, 1.0, 1.0, 1.0, 1.1, 1.0, 1.0, 1.2],
        'kerajinan' => [0.8, 0.8, 0.9, 0.9, 1.0, 1.0, 1.1, 1.4, 1.0, 0.9, 1.0, 1.3],
        'sayur' => [0.8, 0.7, 0.8, 1.0, 1.1, 1.2, 1.2, 1.1, 1.0, 0.9, 0.8, 0.8],
        'padi' => [0.3, 0.4, 1.6, 1.8, 0.5, 0.4, 0.5, 1.5, 1.7, 0.6, 0.4, 0.3],
        'ternak' => [1.0, 0.9, 0.9, 1.0, 1.0, 1.0, 1.0, 1.1, 1.0, 1.0, 1.1, 1.4],
        'dagang' => [1.0, 0.9, 1.0, 1.0, 1.0, 1.0, 1.0, 1.1, 1.0, 1.0, 1.1, 1.4],
    ];

    /**
     * Dua belas kelompok demo untuk presentasi. Nomor kelompok = kunci (mulai 1). Kunci:
     * name, type (kode jenis), village (indeks VILLAGES), leader, sectors, opds, members, founded,
     * tier (rajin | telat: kebiasaan melapor), september (status laporan September 2026, bulan wajib lapor; bawaan
     * verified), products: [nama, satuan, jumlah dasar per bulan, harga per satuan, musim, foto klien|null], dan
     * opsional: email, featured (produk pertama jadi Pilihan distrik), text (kunci SECTOR_TEXT bila beda dari bidang
     * pertama), photos (foto klien kelompok).
     * Foto di database/seeders/media/sementara (nama file = slug) didahulukan bila ada.
     *
     * Kelompok binaan Dinas Perikanan hanya dibina OPD itu (uji: satu OPD tidak berbagi kelompok).
     */
    public const ORGANIZATIONS = [
        1 => [
            'name' => 'Kelompok Tani Kakao Harapan', 'type' => 'kelompok_perkebunan', 'village' => 0,
            'leader' => 'Yakob Rumbiak', 'sectors' => ['Perkebunan', 'Pengolahan Hasil'], 'opds' => ['Pertanian', 'Dinkopdag'],
            'members' => 24, 'founded' => 2014, 'tier' => 'rajin', 'email' => true, 'september' => 'revision', 'featured' => true,
            'products' => [['Kakao fermentasi kering', 'kg', 220, 75_000, 'kakao', null]],
        ],
        2 => [
            'name' => 'KUB Sumber Berkat', 'type' => 'kub', 'village' => 4,
            'leader' => 'Sri Wahyuningsih', 'sectors' => ['Pengolahan Hasil', 'UMKM'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => 12, 'founded' => 2018, 'tier' => 'rajin',
            'products' => [['Keripik pisang', 'bungkus', 240, 15_000, 'olahan', null]],
        ],
        3 => [
            'name' => 'BUMDes Maju Bersama', 'type' => 'bumdes', 'village' => 1,
            'leader' => 'Daniel Kafiar', 'sectors' => ['Perdagangan', 'Perkebunan'], 'opds' => ['Dinkopdag', 'Pertanian', 'DPMPTSP'],
            'members' => 9, 'founded' => 2017, 'tier' => 'rajin', 'september' => 'submitted',
            'products' => [['Beras lokal', 'kg', 900, 13_000, 'dagang', null]],
        ],
        4 => [
            'name' => 'Kelompok Perajin Noken Mama Sejahtera', 'type' => 'umkm', 'village' => 7,
            'leader' => 'Yuliana Ronsumbre', 'sectors' => ['UMKM', 'Lainnya'], 'opds' => ['Dinkopdag'],
            'members' => 15, 'founded' => 2019, 'tier' => 'rajin',
            'products' => [['Noken kulit kayu', 'buah', 8, 250_000, 'kerajinan', 'media/produk/tas-kulit-kayu-2.jpg'], ['Tas dan topi anyaman', 'buah', 20, 150_000, 'kerajinan', 'media/produk/tas-kulit-kayu.jpg']],
        ],
        5 => [
            'name' => 'Kelompok Nelayan Tanjung Pasir Bersatu', 'type' => 'kelompok_perikanan', 'village' => 2,
            'leader' => 'La Ode Hamid', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 18, 'founded' => 2012, 'tier' => 'telat', 'september' => 'draft',
            'products' => [['Ikan asap', 'ekor', 350, 25_000, 'ikan', null]],
            'photos' => ['media/kelompok/perahu-nelayan.jpg'],
        ],
        6 => [
            'name' => 'KUB Abon Ikan Mina Sejahtera', 'type' => 'kub', 'village' => 6,
            'leader' => 'Siti Aminah', 'sectors' => ['Perikanan', 'Pengolahan Hasil'], 'opds' => ['Perikanan'],
            'members' => 14, 'founded' => 2016, 'tier' => 'rajin', 'september' => 'submitted',
            'products' => [['Abon ikan tongkol', 'bungkus', 150, 30_000, 'ikan', 'media/produk/abon-ikan.jpg'], ['Kerupuk ikan', 'bungkus', 180, 12_000, 'olahan', null]],
        ],
        7 => [
            'name' => 'Kelompok Peternak Ayam Kampung Subur Makmur', 'type' => 'kelompok_pertanian', 'village' => 5,
            'leader' => 'Suparman', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian'], 'text' => 'Peternakan',
            'members' => 19, 'founded' => 2015, 'tier' => 'rajin', 'september' => 'submitted',
            'products' => [['Ayam kampung', 'ekor', 90, 85_000, 'ternak', 'media/produk/ayam-kampung.jpg'], ['Telur ayam kampung', 'buah', 600, 3_000, 'ternak', null]],
            'photos' => ['media/kelompok/kandang-ayam.jpg', 'media/kelompok/kandang-ayam-dalam.jpg'],
        ],
        8 => [
            'name' => 'Kelompok Tani Sayur Lembah Subur', 'type' => 'kelompok_pertanian', 'village' => 5,
            'leader' => 'Sutrisno', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian'],
            'members' => 21, 'founded' => 2013, 'tier' => 'rajin', 'september' => 'submitted',
            'products' => [['Cabai rawit', 'kg', 120, 60_000, 'sayur', null]],
        ],
        9 => [
            'name' => 'KDMP Bukit Hijau', 'type' => 'kdmp', 'village' => 0,
            'leader' => 'Hendrik Msen', 'sectors' => ['Perdagangan'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => 40, 'founded' => 2025, 'tier' => 'rajin',
            'products' => [['Beras', 'kg', 1500, 14_000, 'dagang', null]],
        ],
        10 => [
            'name' => 'UMKM Minyak Kelapa Murni Pantai Damai', 'type' => 'umkm', 'village' => 6,
            'leader' => 'Yosina Wanma', 'sectors' => ['UMKM', 'Pengolahan Hasil'], 'opds' => ['Dinkopdag'],
            'members' => 6, 'founded' => 2020, 'tier' => 'rajin',
            'products' => [['Minyak kelapa murni', 'liter', 90, 35_000, 'kelapa', null]],
        ],
        11 => [
            'name' => 'Kelompok Tani Kopi Pegunungan Sungai Bening', 'type' => 'kelompok_perkebunan', 'village' => 7,
            'leader' => 'Yohanes Saiba', 'sectors' => ['Perkebunan', 'Pengolahan Hasil'], 'opds' => ['Pertanian', 'DPMPTSP'],
            'members' => 16, 'founded' => 2011, 'tier' => 'rajin', 'featured' => true,
            'products' => [['Kopi bubuk', 'kg', 45, 120_000, 'kopi', null]],
        ],
        12 => [
            'name' => 'UMKM Sagu Olahan Mama Muara', 'type' => 'umkm', 'village' => 3,
            'leader' => 'Paulina Kaigere', 'sectors' => ['UMKM', 'Pengolahan Hasil'], 'opds' => ['Dinkopdag'],
            'members' => 8, 'founded' => 2021, 'tier' => 'rajin',
            'products' => [['Sagu lempeng', 'bungkus', 250, 15_000, 'sagu', null]],
        ],
    ];

    /**
     * Deskripsi contoh jenis kelembagaan (kartu beranda), per kode MasterDataSeeder::INSTITUTION_TYPES. Maks 50 karakter
     * agar utuh dalam 2 baris di kartu kecil tersempit (form Admin membatasi 90),
     * tanpa tanda pisah. Akronim resmi (BUMDes, KUB, KDMP, KNMP, UMKM) tidak dijabarkan; hanya fungsi umumnya.
     */
    public const INSTITUTION_TYPE_TEXT = [
        'bumdes' => 'Usaha yang dikelola pemerintah kampung.',
        // Jenis berfoto (INSTITUTION_TYPE_PHOTOS) dibuat singkat, maks 30 karakter.
        'kub' => 'Warga berbagi modal dan hasil.',
        'kdmp' => 'Lembaga ekonomi yang melayani usaha anggota.',
        'knmp' => 'Lembaga ekonomi untuk memasarkan hasil anggota.',
        'kelompok_pertanian' => 'Petani yang mengolah lahan dan panen bersama.',
        'kelompok_perkebunan' => 'Pekebun kakao, kopi, pinang.',
        'kelompok_perikanan' => 'Nelayan dan petambak ikan.',
        'umkm' => 'Usaha kecil warga di bidang pangan dan kerajinan.',
        'lainnya' => 'Bentuk kelembagaan ekonomi di luar daftar ini.',
    ];

    /** Foto contoh kartu jenis kelembagaan (database/seeders/photos), sengaja hanya untuk tiga jenis. */
    public const INSTITUTION_TYPE_PHOTOS = [
        'kub' => 'pengemasan.jpg',
        'kelompok_perkebunan' => 'kakao.jpg',
        'kelompok_perikanan' => 'perikanan.jpg',
    ];

    /** Bidang usaha => [templat deskripsi kelompok, ringkasan kegiatan bulanan, capaian]. {produk} dan {kampung} diganti. */
    public const SECTOR_TEXT = [
        'Perkebunan' => [
            'Anggota merawat kebun di sekitar {kampung} dan menjual {produk} bersama agar harga lebih baik. Kelompok punya jadwal panen bergilir dan tempat jemur bersama.',
            ['Panen, fermentasi, dan penjemuran {produk} anggota.', 'Pemangkasan dan sanitasi kebun, lalu penjualan bersama ke pengepul.', 'Penyortiran hasil panen dan penimbangan sebelum dijual.'],
            ['Mutu biji lebih seragam setelah fermentasi bersama.', 'Penjualan bersama memberi harga lebih baik dari bulan lalu.'],
        ],
        'Pertanian' => [
            'Kelompok mengelola lahan bersama di {kampung} dan memasok {produk} ke pasar distrik setiap pekan.',
            ['Penanaman dan perawatan lahan, panen {produk}.', 'Pembersihan saluran air dan panen bertahap.', 'Panen dan pengantaran hasil ke pasar distrik.'],
            ['Hasil panen naik setelah pemakaian pupuk organik.', 'Mendapat pembeli tetap dari warung di distrik.'],
        ],
        'Perikanan' => [
            'Nelayan anggota melaut bersama dan mengolah sebagian hasil tangkapan menjadi {produk}. Pangkalan kelompok di pesisir {kampung}.',
            ['Melaut, penimbangan, dan penjualan {produk}.', 'Pengasapan dan pengemasan hasil tangkapan.', 'Perbaikan jaring lalu kembali melaut.'],
            ['Tangkapan naik karena cuaca bagus.', 'Mulai menjual ke rumah makan di distrik.'],
        ],
        'Pengolahan Hasil' => [
            'Kelompok mengolah hasil kebun dan laut anggota menjadi {produk}, dikemas di rumah produksi di {kampung}.',
            ['Produksi dan pengemasan {produk}.', 'Pembelian bahan baku dari anggota, lalu produksi.', 'Produksi, pelabelan, dan titip jual di kios.'],
            ['Kemasan baru mulai dipakai.', 'Pesanan dari kios di distrik bertambah.'],
        ],
        'UMKM' => [
            'Usaha rumahan di {kampung} yang membuat {produk}. Pemasaran lewat kios, pesanan tetangga, dan acara kampung.',
            ['Produksi {produk} untuk kios dan pesanan.', 'Produksi dan penjualan di pasar mingguan.', 'Memenuhi pesanan acara kampung.'],
            ['Pesanan untuk acara gereja dan hajatan bertambah.', 'Mulai dijual di dua kios baru.'],
        ],
        'Perdagangan' => [
            'Unit usaha milik kampung yang menyediakan {produk} bagi warga {kampung} dan sekitarnya dengan harga terjangkau.',
            ['Pengadaan dan penjualan {produk}.', 'Pengadaan barang dan pencatatan stok bulanan.', 'Penjualan rutin dan rapat pengurus.'],
            ['Stok tersedia sepanjang bulan.', 'Pencatatan keuangan sudah memakai buku kas.'],
        ],
        'Peternakan' => [
            'Anggota memelihara ayam kampung di kandang bersama di {kampung} dan menjual {produk} ke pasar distrik dan pesanan warga.',
            ['Pemberian pakan, pembersihan kandang, dan penjualan {produk}.', 'Vaksinasi ayam dan penimbangan sebelum dijual.', 'Penetasan telur dan pemisahan anak ayam.'],
            ['Kematian ayam turun setelah kandang diperbaiki.', 'Mendapat pesanan tetap dari rumah makan di distrik.'],
        ],
        'Lainnya' => [
            'Mama-mama perajin di {kampung} membuat {produk} dari bahan alam. Dijual saat ada acara dan lewat pesanan.',
            ['Pengumpulan bahan dan pembuatan {produk}.', 'Pembuatan {produk} untuk pesanan.', 'Pameran kecil di balai kampung.'],
            ['Pesanan dari luar distrik mulai masuk.', 'Motif baru disukai pembeli.'],
        ],
    ];

    /** Catatan tindak lanjut per status. */
    public const FOLLOW_UP_NOTES = [
        'reviewed' => 'Sudah dibaca. Petugas lapangan akan mengecek ke kelompok.',
        'planned' => 'Masuk rencana kegiatan bulan depan bersama kelompok lain.',
        'in_progress' => 'Petugas sudah berkunjung; bahan pendampingan sedang disiapkan.',
        'done' => 'Sudah ditindaklanjuti lewat kunjungan dan pelatihan singkat.',
        'unable' => 'Belum ada anggaran tahun ini. Diusulkan untuk tahun depan.',
    ];
}
