<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jadwal (butuh cron "schedule:run" setiap menit di server, lihat CLAUDE.md Bagian 12).
| Notifikasi tenggat laporan dicek setiap hari; perintahnya sendiri memilih tahap menurut tanggal
| (awal, H-3 tenggat, H+1 tenggat) dan tidak mengirim apa pun di hari lain.
*/
Schedule::command('simker:pengingat-laporan')
    ->dailyAt(config('simker.reports.reminder_time'))
    ->timezone(config('simker.reports.timezone'))
    ->withoutOverlapping();

// Cadangan database harian (simker:backup-db), 14 file terakhir di storage/app/private/backups.
Schedule::command('simker:backup-db')
    ->dailyAt(config('simker.backup.time'))
    ->timezone(config('simker.reports.timezone'))
    ->withoutOverlapping();
