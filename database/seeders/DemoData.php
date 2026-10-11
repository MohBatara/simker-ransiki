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
        'dagang' => [1.0, 0.9, 1.0, 1.0, 1.0, 1.0, 1.0, 1.1, 1.0, 1.0, 1.1, 1.4],
    ];

    /**
     * Kelompok demo. Nomor kelompok = urutan di daftar (mulai 1). Kunci:
     * name, type (kode jenis), village (indeks VILLAGES), leader, sectors, opds, members, founded,
     * tier (rajin | biasa | telat: kebiasaan melapor), products: [nama, satuan, jumlah dasar per bulan,
     * harga per satuan, musim, foto|null], dan opsional: status + since (bulan mulai tidak aktif),
     * public, starts (wajib lapor mulai), email.
     *
     * Kelompok binaan Dinas Perikanan hanya dibina OPD itu (uji: satu OPD tidak berbagi kelompok).
     */
    public const ORGANIZATIONS = [
        1 => [
            'name' => 'Kelompok Tani Kakao Harapan', 'type' => 'kelompok_perkebunan', 'village' => 0,
            'leader' => 'Yakob Rumbiak', 'sectors' => ['Perkebunan', 'Pengolahan Hasil'], 'opds' => ['Pertanian', 'Dinkopdag'],
            'members' => 24, 'founded' => 2014, 'tier' => 'rajin', 'email' => true,
            'products' => [['Kakao fermentasi kering', 'kg', 220, 75_000, 'kakao', 'kakao.jpg'], ['Pinang kering', 'kg', 60, 18_000, 'pinang', null]],
        ],
        2 => [
            'name' => 'KUB Sumber Berkat', 'type' => 'kub', 'village' => 4,
            'leader' => 'Sri Wahyuningsih', 'sectors' => ['Pengolahan Hasil', 'UMKM'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => 12, 'founded' => 2018, 'tier' => 'rajin',
            'products' => [['Keripik pisang', 'bungkus', 240, 15_000, 'olahan', 'keripik.jpg'], ['Keripik keladi', 'bungkus', 120, 15_000, 'olahan', null]],
        ],
        3 => [
            'name' => 'BUMDes Maju Bersama', 'type' => 'bumdes', 'village' => 1,
            'leader' => 'Daniel Kafiar', 'sectors' => ['Perdagangan', 'Perkebunan'], 'opds' => ['Dinkopdag', 'Pertanian', 'DPMPTSP'],
            'members' => null, 'founded' => 2017, 'tier' => 'biasa', 'email' => true,
            'products' => [['Beras lokal', 'kg', 900, 13_000, 'dagang', null], ['Kakao kering (beli dari petani)', 'kg', 400, 72_000, 'kakao', null]],
        ],
        4 => [
            'name' => 'Kelompok Perajin Noken Mama Sejahtera', 'type' => 'lainnya', 'village' => 7,
            'leader' => 'Yuliana Ronsumbre', 'sectors' => ['Lainnya', 'UMKM'], 'opds' => ['Dinkopdag'],
            'members' => 15, 'founded' => 2019, 'tier' => 'telat',
            'products' => [['Noken benang', 'buah', 25, 150_000, 'kerajinan', null], ['Noken kulit kayu', 'buah', 8, 250_000, 'kerajinan', null]],
        ],
        5 => [
            'name' => 'KNMP Mina Bahari', 'type' => 'knmp', 'village' => 2,
            'leader' => 'Markus Sawaki', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 31, 'founded' => 2025, 'tier' => 'biasa',
            'products' => [['Ikan segar', 'kg', 600, 35_000, 'ikan', 'perikanan.jpg'], ['Ikan asap', 'ekor', 300, 25_000, 'ikan', null]],
        ],
        6 => [
            'name' => 'Kelompok Nelayan Tanjung Pasir Bersatu', 'type' => 'kelompok_perikanan', 'village' => 2,
            'leader' => 'La Ode Hamid', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 18, 'founded' => 2012, 'tier' => 'rajin',
            'products' => [['Ikan asap', 'ekor', 350, 25_000, 'ikan', null], ['Abon ikan', 'bungkus', 80, 30_000, 'ikan', null]],
        ],
        7 => [
            'name' => 'Kelompok Tani Sayur Lembah Subur', 'type' => 'kelompok_pertanian', 'village' => 5,
            'leader' => 'Sutrisno', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian'],
            'members' => 20, 'founded' => 2010, 'tier' => 'rajin',
            'products' => [['Cabai rawit', 'kg', 120, 60_000, 'sayur', null], ['Kangkung', 'ikat', 800, 5_000, 'sayur', null], ['Tomat', 'kg', 300, 15_000, 'sayur', null]],
        ],
        8 => [
            'name' => 'UMKM Minyak Kelapa Murni Pantai Damai', 'type' => 'umkm', 'village' => 6,
            'leader' => 'Yosina Wanma', 'sectors' => ['UMKM', 'Pengolahan Hasil'], 'opds' => ['Dinkopdag'],
            'members' => 5, 'founded' => 2020, 'tier' => 'telat', 'status' => 'inactive', 'since' => '2026-05-01',
            'products' => [['Minyak kelapa', 'liter', 90, 35_000, 'kelapa', null]],
        ],
        9 => [
            'name' => 'KDMP Bukit Hijau', 'type' => 'kdmp', 'village' => 0,
            'leader' => 'Hendrik Msen', 'sectors' => ['Perdagangan'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => 64, 'founded' => 2026, 'tier' => 'biasa', 'starts' => '2026-07-01',
            'products' => [['Beras', 'kg', 1500, 14_000, 'dagang', null], ['Minyak goreng', 'liter', 400, 19_000, 'dagang', null]],
        ],
        10 => [
            'name' => 'Kelompok Pembudidaya Kepiting Bakau Muara Indah', 'type' => 'kelompok_perikanan', 'village' => 3,
            'leader' => 'Petrus Iba', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 11, 'founded' => 2021, 'tier' => 'telat',
            'products' => [['Kepiting bakau', 'kg', 80, 85_000, 'kepiting', null]],
        ],
        11 => [
            'name' => 'Kelompok Usaha Bersama Pengolahan Hasil Perikanan dan Kelautan Mina Sejahtera Pantai Damai', 'type' => 'kub', 'village' => 6,
            'leader' => 'Siti Aminah', 'sectors' => ['Perikanan', 'Pengolahan Hasil'], 'opds' => ['Perikanan'],
            'members' => 14, 'founded' => 2016, 'tier' => 'biasa',
            'products' => [['Abon ikan', 'bungkus', 150, 30_000, 'ikan', null], ['Ikan asap', 'ekor', 200, 25_000, 'ikan', null], ['Kerupuk ikan', 'bungkus', 180, 12_000, 'olahan', null]],
        ],
        12 => [
            'name' => 'Kelompok Tani Kopi Pegunungan Sungai Bening', 'type' => 'kelompok_perkebunan', 'village' => 7,
            'leader' => 'Yohanes Saiba', 'sectors' => ['Perkebunan', 'Pengolahan Hasil'], 'opds' => ['Pertanian', 'DPMPTSP'],
            'members' => 17, 'founded' => 2015, 'tier' => 'biasa',
            'products' => [['Kopi bubuk', 'kg', 45, 120_000, 'kopi', null], ['Kopi biji sangrai', 'kg', 30, 150_000, 'kopi', null]],
        ],
        13 => [
            'name' => 'Kelompok Sagu Lestari', 'type' => 'kelompok_pertanian', 'village' => 3,
            'leader' => 'Elias Rumere', 'sectors' => ['Pertanian', 'Pengolahan Hasil'], 'opds' => ['Pertanian'],
            'members' => 9, 'founded' => 2013, 'tier' => 'telat',
            'products' => [['Sagu basah (tumang)', 'kg', 700, 8_000, 'sagu', null], ['Sagu lempeng', 'bungkus', 200, 15_000, 'sagu', null]],
        ],
        14 => [
            'name' => 'UMKM Ayu', 'type' => 'umkm', 'village' => 4,
            'leader' => 'Ni Ketut Ayu Widiastuti', 'sectors' => ['UMKM'], 'opds' => ['Dinkopdag'],
            'members' => 3, 'founded' => 2022, 'tier' => 'rajin',
            'products' => [['Keripik singkong', 'bungkus', 150, 12_000, 'olahan', null]],
        ],
        15 => [
            'name' => 'BUMDes Bersatu Membangun Kampung Lembah Subur Mandiri Sejahtera', 'type' => 'bumdes', 'village' => 5,
            'leader' => 'I Wayan Sudarta', 'sectors' => ['Perdagangan', 'Pertanian', 'Pengolahan Hasil'], 'opds' => ['Dinkopdag', 'Pertanian', 'DPMPTSP'],
            'members' => null, 'founded' => 2016, 'tier' => 'biasa',
            'products' => [['Beras lokal', 'kg', 1200, 13_000, 'padi', null], ['Pupuk organik', 'karung', 60, 50_000, 'dagang', null]],
        ],
        16 => [
            'name' => 'Kelompok Tani Pinang Sumber Air', 'type' => 'kelompok_perkebunan', 'village' => 1,
            'leader' => 'Obeth Ullo', 'sectors' => ['Perkebunan'], 'opds' => ['Pertanian'],
            'members' => 13, 'founded' => 2019, 'tier' => 'telat', 'public' => false,
            'products' => [['Pinang kering', 'kg', 180, 18_000, 'pinang', null], ['Pinang muda', 'ikat', 300, 10_000, 'pinang', null]],
        ],
        17 => [
            'name' => 'Kelompok Nelayan Pantai Damai', 'type' => 'kelompok_perikanan', 'village' => 6,
            'leader' => 'Nikolas Rumbewas', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 16, 'founded' => 2011, 'tier' => 'biasa', 'status' => 'inactive', 'since' => '2026-06-01',
            'products' => [['Ikan segar', 'kg', 450, 35_000, 'ikan', null]],
        ],
        18 => [
            'name' => 'KUB Rio', 'type' => 'kub', 'village' => 2,
            'leader' => 'Andi Baso', 'sectors' => ['Pengolahan Hasil', 'Perdagangan'], 'opds' => ['Dinkopdag'],
            'members' => 8, 'founded' => 2020, 'tier' => 'biasa',
            'products' => [['Kopra', 'kg', 500, 9_000, 'kelapa', null]],
        ],
        19 => [
            'name' => 'Kelompok Tani Kakao Mekar Jaya', 'type' => 'kelompok_perkebunan', 'village' => 4,
            'leader' => 'Bambang Suryanto', 'sectors' => ['Perkebunan'], 'opds' => ['Pertanian', 'DPMPTSP'],
            'members' => 22, 'founded' => 2009, 'tier' => 'rajin',
            'products' => [['Kakao fermentasi kering', 'kg', 180, 75_000, 'kakao', 'panen-kakao.jpg']],
        ],
        20 => [
            'name' => 'Kelompok Wanita Pengolah Pisang Bukit Hijau', 'type' => 'umkm', 'village' => 0,
            'leader' => 'Martha Inden', 'sectors' => ['UMKM', 'Pengolahan Hasil'], 'opds' => ['Dinkopdag', 'Pertanian'],
            'members' => 10, 'founded' => 2021, 'tier' => 'biasa',
            'products' => [['Keripik pisang', 'bungkus', 160, 15_000, 'olahan', null], ['Sale pisang', 'bungkus', 60, 20_000, 'olahan', null]],
        ],
        // Sengaja mirip "KUB Sumber Berkat" di kampung yang sama: mengisi halaman "Data ganda".
        21 => [
            'name' => 'Kelompok Sumber Berkat', 'type' => 'umkm', 'village' => 4,
            'leader' => 'Siti Rahayu', 'sectors' => ['UMKM'], 'opds' => ['Dinkopdag'],
            'members' => 6, 'founded' => 2026, 'tier' => 'telat', 'starts' => '2026-09-01', 'public' => false,
            'products' => [['Keripik pisang', 'bungkus', 60, 15_000, 'olahan', null]],
        ],
        22 => [
            'name' => 'Kelompok Tani Hortikultura Subur Makmur', 'type' => 'kelompok_pertanian', 'village' => 5,
            'leader' => 'Suparman', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian'],
            'members' => 19, 'founded' => 2012, 'tier' => 'rajin',
            'products' => [['Sawi', 'ikat', 600, 5_000, 'sayur', null], ['Cabai keriting', 'kg', 90, 50_000, 'sayur', null], ['Kacang panjang', 'ikat', 400, 5_000, 'sayur', null]],
        ],
        23 => [
            'name' => 'Kelompok Perajin Anyaman Pandan', 'type' => 'lainnya', 'village' => 7,
            'leader' => 'Ruth Dowansiba', 'sectors' => ['Lainnya'], 'opds' => ['Dinkopdag'],
            'members' => 7, 'founded' => 2015, 'tier' => 'biasa', 'status' => 'closed',
            'products' => [['Tikar pandan', 'lembar', 20, 75_000, 'kerajinan', null]],
        ],
        24 => [
            'name' => 'KNMP Samudra Biru', 'type' => 'knmp', 'village' => 3,
            'leader' => 'Yulius Wonggor', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 27, 'founded' => 2025, 'tier' => 'rajin',
            'products' => [['Ikan segar', 'kg', 500, 33_000, 'ikan', null], ['Udang segar', 'kg', 60, 70_000, 'ikan', null]],
        ],
        25 => [
            'name' => 'Kelompok Tani Padi Sawah Sri Rejeki', 'type' => 'kelompok_pertanian', 'village' => 4,
            'leader' => 'Ahmad Fauzi', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian'],
            'members' => 28, 'founded' => 2008, 'tier' => 'biasa',
            'products' => [['Beras', 'kg', 1000, 13_000, 'padi', 'pertanian.jpg']],
        ],
        26 => [
            'name' => 'UMKM Kue dan Roti Berkah', 'type' => 'umkm', 'village' => 1,
            'leader' => 'Nurhayati', 'sectors' => ['UMKM'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => 4, 'founded' => 2019, 'tier' => 'rajin', 'email' => true,
            'products' => [['Roti manis', 'bungkus', 400, 10_000, 'olahan', null], ['Kue bolu', 'buah', 60, 45_000, 'olahan', null]],
        ],
        27 => [
            'name' => 'Kelompok Perkebunan Kelapa Tanjung', 'type' => 'kelompok_perkebunan', 'village' => 2,
            'leader' => 'Frans Kaiway', 'sectors' => ['Perkebunan', 'Pengolahan Hasil'], 'opds' => ['Pertanian', 'Dinkopdag'],
            'members' => 21, 'founded' => 2014, 'tier' => 'biasa',
            'products' => [['Kopra', 'kg', 800, 9_000, 'kelapa', null], ['Minyak kelapa', 'liter', 60, 35_000, 'kelapa', null]],
        ],
        28 => [
            'name' => 'Kelompok Pembudidaya Ikan Air Tawar Sungai Bening', 'type' => 'kelompok_perikanan', 'village' => 7,
            'leader' => 'Gerson Mofu', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 10, 'founded' => 2026, 'tier' => 'biasa', 'starts' => '2026-07-01',
            'products' => [['Ikan nila', 'kg', 150, 40_000, 'kepiting', null], ['Ikan lele', 'kg', 200, 30_000, 'kepiting', null]],
        ],
        29 => [
            'name' => 'Kelompok Tani Kakao Sumber Air', 'type' => 'kelompok_perkebunan', 'village' => 1,
            'leader' => 'Simon Rumansara', 'sectors' => ['Perkebunan'], 'opds' => ['Pertanian'],
            'members' => 12, 'founded' => 2017, 'tier' => 'telat', 'status' => 'inactive', 'since' => '2026-08-01',
            'products' => [['Kakao asalan', 'kg', 250, 55_000, 'kakao', null]],
        ],
        30 => [
            'name' => 'BUMDes Pantai Damai Sejahtera', 'type' => 'bumdes', 'village' => 6,
            'leader' => 'Lukas Ayok', 'sectors' => ['Perdagangan', 'Lainnya'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => null, 'founded' => 2018, 'tier' => 'biasa', 'public' => false,
            'products' => [['Es balok', 'buah', 900, 5_000, 'ikan', null], ['Beras', 'kg', 600, 14_000, 'dagang', null]],
        ],
        31 => [
            'name' => 'UMKM Noken Kasih', 'type' => 'umkm', 'village' => 3,
            'leader' => 'Debora Korwa', 'sectors' => ['UMKM', 'Lainnya'], 'opds' => ['Dinkopdag'],
            'members' => 4, 'founded' => 2023, 'tier' => 'telat',
            'products' => [['Noken benang', 'buah', 30, 150_000, 'kerajinan', null]],
        ],
        32 => [
            'name' => 'Kelompok Tani Jagung dan Kacang Tanah Mekar Jaya', 'type' => 'kelompok_pertanian', 'village' => 4,
            'leader' => 'I Made Suarna', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian', 'DPMPTSP'],
            'members' => 23, 'founded' => 2011, 'tier' => 'biasa',
            'products' => [['Jagung pipil', 'kg', 1200, 6_000, 'padi', null], ['Kacang tanah', 'kg', 200, 25_000, 'padi', null]],
        ],
        33 => [
            'name' => 'KUB Ikan Asap Mama Tanjung', 'type' => 'kub', 'village' => 2,
            'leader' => 'Mince Korwa', 'sectors' => ['Perikanan', 'Pengolahan Hasil'], 'opds' => ['Perikanan'],
            'members' => 9, 'founded' => 2018, 'tier' => 'rajin',
            'products' => [['Ikan asap', 'ekor', 400, 25_000, 'ikan', null]],
        ],
        34 => [
            'name' => 'KUB Cokelat Olahan Lembah Subur', 'type' => 'kub', 'village' => 5,
            'leader' => 'Yustina Seran', 'sectors' => ['Pengolahan Hasil', 'Perkebunan'], 'opds' => ['Pertanian', 'Dinkopdag'],
            'members' => 8, 'founded' => 2022, 'tier' => 'biasa',
            'products' => [['Cokelat batang', 'bungkus', 120, 25_000, 'olahan', 'pengemasan.jpg'], ['Bubuk kakao', 'bungkus', 80, 30_000, 'olahan', null]],
        ],
        35 => [
            'name' => 'Kelompok Pemuda Tani Milenial Bukit Hijau', 'type' => 'kelompok_pertanian', 'village' => 0,
            'leader' => 'Kevin Ayomi', 'sectors' => ['Pertanian'], 'opds' => ['Pertanian'],
            'members' => 11, 'founded' => 2026, 'tier' => 'biasa', 'starts' => '2026-09-01', 'public' => false,
            'products' => [['Semangka', 'buah', 300, 20_000, 'sayur', null], ['Cabai rawit', 'kg', 60, 60_000, 'sayur', null]],
        ],
        36 => [
            'name' => 'UMKM Sagu Olahan Mama Muara', 'type' => 'umkm', 'village' => 3,
            'leader' => 'Paulina Kaigere', 'sectors' => ['UMKM', 'Pengolahan Hasil'], 'opds' => ['Dinkopdag'],
            'members' => 5, 'founded' => 2020, 'tier' => 'rajin',
            'products' => [['Sagu lempeng', 'bungkus', 250, 15_000, 'sagu', null], ['Bagea sagu', 'bungkus', 150, 20_000, 'olahan', null]],
        ],
        37 => [
            'name' => 'Kelompok Nelayan Muara Indah Jaya', 'type' => 'kelompok_perikanan', 'village' => 3,
            'leader' => 'Abdul Rahman Wally', 'sectors' => ['Perikanan'], 'opds' => ['Perikanan'],
            'members' => 14, 'founded' => 2013, 'tier' => 'telat',
            'products' => [['Ikan segar', 'kg', 300, 35_000, 'ikan', null], ['Kepiting bakau', 'kg', 40, 85_000, 'kepiting', null]],
        ],
        38 => [
            'name' => 'Kelompok Tani Kopi dan Kakao Sungai Bening Atas', 'type' => 'kelompok_perkebunan', 'village' => 7,
            'leader' => 'Demianus Mandowen', 'sectors' => ['Perkebunan'], 'opds' => ['Pertanian', 'DPMPTSP'],
            'members' => 16, 'founded' => 2016, 'tier' => 'biasa',
            'products' => [['Kopi biji kering', 'kg', 90, 60_000, 'kopi', null], ['Kakao fermentasi kering', 'kg', 120, 75_000, 'kakao', null]],
        ],
        39 => [
            'name' => 'KDMP Lembah Subur', 'type' => 'kdmp', 'village' => 5,
            'leader' => 'Teguh Prasetyo', 'sectors' => ['Perdagangan'], 'opds' => ['Dinkopdag'],
            'members' => 58, 'founded' => 2026, 'tier' => 'rajin', 'starts' => '2026-07-01',
            'products' => [['Beras', 'kg', 1300, 14_000, 'dagang', null], ['Gula pasir', 'kg', 300, 18_000, 'dagang', null]],
        ],
        40 => [
            'name' => 'UMKM Bumbu Dapur dan Sambal Ikan Ibu Ratna', 'type' => 'umkm', 'village' => 1,
            'leader' => 'Ratna Handayani', 'sectors' => ['UMKM'], 'opds' => ['Dinkopdag', 'DPMPTSP'],
            'members' => 3, 'founded' => 2021, 'tier' => 'biasa', 'email' => true,
            'products' => [['Sambal ikan', 'buah', 100, 35_000, 'olahan', null]],
        ],
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
        'Lainnya' => [
            'Mama-mama perajin di {kampung} membuat {produk} dari bahan alam. Dijual saat ada acara dan lewat pesanan.',
            ['Pengumpulan bahan dan pembuatan {produk}.', 'Pembuatan {produk} untuk pesanan.', 'Pameran kecil di balai kampung.'],
            ['Pesanan dari luar distrik mulai masuk.', 'Motif baru disukai pembeli.'],
        ],
    ];

    /** [kategori, uraian] masalah yang sering muncul. */
    public const PROBLEMS = [
        ['produksi', 'Hama penggerek buah menyerang sebagian kebun anggota.'],
        ['pemasaran', 'Harga dari pengepul turun dibanding bulan lalu.'],
        ['transportasi', 'Ongkos angkut hasil ke kota naik karena jalan rusak.'],
        ['modal', 'Modal untuk membeli bahan baku dan kemasan terbatas.'],
        ['peralatan', 'Mesin perajang rusak dan belum diperbaiki.'],
        ['bahan_baku', 'Bahan baku sulit didapat karena musim panen belum tiba.'],
        ['sdm', 'Beberapa anggota muda bekerja di luar kampung.'],
        ['legalitas', 'Belum punya izin edar, sehingga sulit masuk toko.'],
        ['lainnya', 'Cuaca buruk, anggota tidak bisa bekerja selama dua minggu.'],
    ];

    /** [kategori, uraian] kebutuhan dukungan. */
    public const NEEDS = [
        ['pelatihan', 'Pelatihan fermentasi dan sortasi agar mutu lebih seragam.'],
        ['bantuan_alat', 'Bantuan mesin pengering atau para-para jemur.'],
        ['pendampingan', 'Pendampingan pembukuan sederhana untuk bendahara.'],
        ['pemasaran', 'Bantuan kemasan dan label produk.'],
        ['akses_pasar', 'Dihubungkan dengan pembeli tetap di kota.'],
        ['legalitas', 'Pendampingan pengurusan PIRT dan sertifikat halal.'],
        ['modal', 'Pinjaman modal bergulir untuk pembelian bahan baku.'],
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
