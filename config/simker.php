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

];
