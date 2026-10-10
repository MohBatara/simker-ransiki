<?php

namespace App\Support;

use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Pesan pengingat yang disalin OPD/Admin untuk dikirim sendiri (mis. lewat WhatsApp) ke pengurus
 * kelompok yang belum mengajukan laporan.
 */
class ReminderMessage
{
    public static function for(Organization $organization, CarbonInterface $period, ?MonthlyReport $report, User $sender): string
    {
        $month = Format::month($period);
        $deadline = MonthlyReport::deadlineFor($period);

        $status = $report
            ? "Laporan bulanan {$month} masih berupa draf dan belum diajukan."
            : "Laporan bulanan {$month} belum kami terima.";

        $deadlineLine = MonthlyReport::isPastDeadline($period)
            ? 'Tenggat pengiriman sudah lewat ('.Format::date($deadline).'). Mohon segera diajukan.'
            : 'Tenggat pengiriman: '.Format::date($deadline).', pukul 23.59 WIT.';

        $signature = $sender->isOpd() && $sender->opd ? $sender->opd->name : 'Admin Distrik Ransiki';

        return implode("\n", [
            "Yth. pengurus {$organization->name},",
            '',
            $status,
            $deadlineLine,
            'Silakan masuk ke '.route('login').' untuk mengisi dan mengajukan laporan.',
            '',
            'Terima kasih.',
            $signature,
        ]);
    }
}
