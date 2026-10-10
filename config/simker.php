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
        // Pengingat untuk kelompok yang belum mengajukan laporan bulan lalu (simker:pengingat-laporan).
        // Hari pertama = tahap "awal" (laporan sudah bisa diajukan), hari kedua = tahap "akhir" (tenggat dekat).
        'reminder_days' => [1, 8],
        'reminder_time' => '08:00',
    ],

];
