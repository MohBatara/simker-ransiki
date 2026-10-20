<?php

return [

    /*
    | Laporan bulanan. Tenggat: tanggal `deadline_day` bulan berikutnya pukul 23.59 di zona `timezone`.
    | Laporan bulan berjalan baru bisa diajukan mulai tanggal 1 bulan berikutnya.
    */
    'reports' => [
        'deadline_day' => (int) env('SIMKER_REPORT_DEADLINE_DAY', 10),
        'timezone' => 'Asia/Jayapura',
        // Simpan otomatis di form laporan, dalam detik.
        'autosave_seconds' => 30,
        // Notifikasi tenggat (simker:pengingat-laporan, dijalankan harian pukul `reminder_time` WIT):
        // tahap "awal" pada tanggal `reminder_first_day` (laporan bulan lalu sudah bisa diajukan),
        // "akhir" H-`reminder_days_before` tenggat ke kelompok yang belum mengajukan, dan
        // "terlambat" H+`overdue_days_after` tenggat ke kelompok terlambat + OPD terkait.
        'reminder_first_day' => 1,
        'reminder_days_before' => 3,
        'overdue_days_after' => 1,
        'reminder_time' => '08:00',
    ],

    /*
    | Halaman Statistik dan grafik dasbor Kelompok. Bulan di grafik tren pendapatan dianggap "sementara"
    | (garis putus-putus) bila belum lewat tenggat + `provisional_days_after_deadline` hari, ATAU laporan
    | terverifikasinya kurang dari `provisional_min_coverage` (0–1) dari kelompok wajib lapor bulan itu.
    */
    'statistics' => [
        'provisional_days_after_deadline' => 14,
        'provisional_min_coverage' => 0.8,
    ],

    /*
    | Peringatan data ganda kelompok (App\Support\DuplicateCandidates). Nama dianggap mirip bila kemiripannya
    | (similar_text, setelah awalan umum dibuang) paling sedikit `min_similarity` persen.
    */
    'duplicates' => [
        'min_similarity' => (int) env('SIMKER_DUPLICATE_MIN_SIMILARITY', 85),
    ],

    /*
    | Pemisahan tugas: akun yang mengisi, mengubah, atau mengajukan laporan tidak boleh memverifikasinya.
    */
    'verification' => [
        'separate_duties' => (bool) env('SIMKER_SEPARATE_DUTIES', true),
    ],

    /*
    | Tindak lanjut masalah & kebutuhan. Kartu dasbor "Kebutuhan belum ditindaklanjuti" menghitung kebutuhan
    | berstatus Baru/Ditinjau yang tidak berubah lebih dari `stale_days` hari.
    */
    'follow_up' => [
        'stale_days' => 30,
    ],

    /*
    | Produksi: alihkan HTTP ke HTTPS dan kirim HSTS (App\Http\Middleware\SecureResponse). Matikan hanya
    | sementara bila sertifikat SSL belum aktif.
    */
    'security' => [
        'force_https' => (bool) env('SIMKER_FORCE_HTTPS', true),
    ],

    /*
    | Cadangan database (simker:backup-db, dijadwalkan harian pukul `time` WIT). File .sql.gz di disk `local`
    | folder `path`; hanya `keep` file terbaru yang disimpan. `mysqldump` = perintah/path mysqldump di server.
    */
    'backup' => [
        'path' => 'backups',
        'keep' => (int) env('SIMKER_BACKUP_KEEP', 14),
        'time' => '02:00',
        'mysqldump' => env('SIMKER_MYSQLDUMP', 'mysqldump'),
        'timeout' => 600,
    ],

    /*
    | Foto resmi distrik dari database/seeders/media (hero beranda, Profil Ransiki) dipasang OfficialPhotoSeeder.
    | Tes mematikannya (phpunit.xml) agar tidak menulis ke storage asli dan tetap cepat.
    */
    'seed_photos' => (bool) env('SIMKER_SEED_PHOTOS', true),

    /*
    |--------------------------------------------------------------------------
    | Ikon bidang usaha di website publik (App\Support\PublicIcons)
    |--------------------------------------------------------------------------
    | Kunci = slug nama bidang usaha (Str::slug, mis. "Pengolahan Hasil" → "pengolahan-hasil"); nilai = nama file
    | di resources/svg/lucide. Bidang tanpa entri memakai `default`. Ikon baru: salin isi <svg> dari lucide.dev.
    */
    'public_icons' => [
        'sectors' => [
            'pertanian' => 'sprout',
            'perkebunan' => 'trees',
            'perikanan' => 'fish',
            'pengolahan-hasil' => 'cooking-pot',
            'umkm' => 'store',
            'perdagangan' => 'shopping-basket',
            'lainnya' => 'box',
        ],
        'default' => 'box',
    ],

];
