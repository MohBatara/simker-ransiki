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

];
