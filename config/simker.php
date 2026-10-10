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
    ],

];
