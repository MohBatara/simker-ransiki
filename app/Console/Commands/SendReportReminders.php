<?php

namespace App\Console\Commands;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportReminder;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat ke kelompok wajib lapor yang belum mengajukan laporan bulan lalu (belum dibuat atau masih
 * draf). Kelompok tutup tidak diingatkan. Aman dijalankan berulang: akun yang sudah menerima pengingat
 * tahap yang sama untuk periode itu dilewati.
 */
#[Signature('simker:pengingat-laporan {--tahap= : awal atau akhir (bawaan: menurut tanggal hari ini)} {--periode= : Y-m (bawaan: bulan lalu)}')]
#[Description('Kirim notifikasi pengingat laporan bulanan ke kelompok yang belum mengajukan')]
class SendReportReminders extends Command
{
    public function handle(): int
    {
        $stage = $this->option('tahap') ?? (now()->day === config('simker.reports.reminder_days')[0] ? 'awal' : 'akhir');

        if (! in_array($stage, ['awal', 'akhir'], true)) {
            $this->error('Tahap harus "awal" atau "akhir".');

            return self::INVALID;
        }

        $input = $this->option('periode');
        if ($input !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input)) {
            $this->error('Periode harus berformat Y-m, misalnya 2026-09.');

            return self::INVALID;
        }
        $period = $input ? CarbonImmutable::createFromFormat('!Y-m', $input) : ReportingPeriod::due();

        $organizations = Organization::obligatedFor($period)
            ->whereDoesntHave('monthlyReports', fn ($query) => $query
                ->whereDate('period', $period->format('Y-m-d'))
                ->where('status', '!=', ReportStatus::Draft))
            ->with(['users' => fn ($query) => $query->where('role', Role::Kelompok)->where('is_active', true)])
            ->get();

        $alreadyReminded = $this->alreadyReminded($organizations->pluck('users')->flatten()->pluck('id'), $period, $stage);
        $sentUsers = 0;
        $sentOrganizations = 0;

        foreach ($organizations as $organization) {
            $recipients = $organization->users->reject(fn (User $user) => $alreadyReminded->contains($user->id));

            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::send($recipients, new ReportReminder($organization, $period, $stage));
            $sentUsers += $recipients->count();
            $sentOrganizations++;
        }

        $this->info($sentUsers
            ? 'Pengingat laporan '.Format::month($period)." dikirim ke {$sentUsers} akun dari {$sentOrganizations} kelompok."
            : 'Tidak ada kelompok yang perlu diingatkan.');

        return self::SUCCESS;
    }

    private function alreadyReminded($userIds, CarbonImmutable $period, string $stage)
    {
        return DatabaseNotification::query()
            ->where('type', ReportReminder::class)
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', $userIds)
            ->where('data->period', $period->format('Y-m'))
            ->where('data->stage', $stage)
            ->pluck('notifiable_id');
    }
}
