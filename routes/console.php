<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jadwal (butuh cron "schedule:run" setiap menit di server, lihat CLAUDE.md Bagian 12).
| Pengingat laporan: hari pertama tahap "awal", hari kedua tahap "akhir", zona waktu WIT.
*/
[$firstDay, $secondDay] = config('simker.reports.reminder_days');
$time = config('simker.reports.reminder_time');

Schedule::command('simker:pengingat-laporan --tahap=awal')->monthlyOn($firstDay, $time)->timezone('Asia/Jayapura');
Schedule::command('simker:pengingat-laporan --tahap=akhir')->monthlyOn($secondDay, $time)->timezone('Asia/Jayapura');
