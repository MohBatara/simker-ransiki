<?php

namespace App\Console\Commands;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ReportOverdue;
use App\Notifications\ReportReminder;
use App\Support\Dashboards\ReportingPeriod;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Notifikasi tenggat laporan bulan lalu, dijalankan harian. Tahap dipilih menurut tanggal hari ini
 * (WIT) bila --tahap tidak diisi:
 * - awal: tanggal pertama bulan, ke kelompok wajib lapor yang belum mengajukan (belum dibuat atau draf);
 * - akhir: H-3 tenggat, ke kelompok yang sama;
 * - terlambat: H+1 tenggat, ke kelompok terlambat (Organization::overdueFor) dan peninjaunya
 *   (OPD terkait, atau Admin bila kelompok tanpa OPD pembina).
 * Hanya akun aktif. Kelompok tutup tidak dihubungi. Aman dijalankan berulang: akun yang sudah menerima
 * notifikasi tahap yang sama untuk kelompok dan periode itu dilewati.
 */
#[Signature('simker:pengingat-laporan {--tahap= : awal, akhir, atau terlambat (bawaan: menurut tanggal hari ini)} {--periode= : Y-m (bawaan: bulan lalu)}')]
#[Description('Kirim notifikasi tenggat laporan bulanan (pengingat dan keterlambatan)')]
class SendReportReminders extends Command
{
    private const STAGES = ['awal', 'akhir', 'terlambat'];

    private ?Collection $reviewerCache = null;

    public function handle(): int
    {
        $input = $this->option('periode');
        if ($input !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input)) {
            $this->error('Periode harus berformat Y-m, misalnya 2026-09.');

            return self::INVALID;
        }
        $period = $input ? CarbonImmutable::createFromFormat('!Y-m', $input) : ReportingPeriod::due();

        $stage = $this->option('tahap') ?? self::stageForToday($period);

        if ($stage === null) {
            $this->info('Hari ini tidak ada jadwal notifikasi tenggat.');

            return self::SUCCESS;
        }

        if (! in_array($stage, self::STAGES, true)) {
            $this->error('Tahap harus "awal", "akhir", atau "terlambat".');

            return self::INVALID;
        }

        $organizations = $this->organizations($stage, $period);
        $alreadySent = $this->alreadySent($stage, $period, $organizations->modelKeys());
        $sentUsers = 0;
        $sentOrganizations = 0;

        foreach ($organizations as $organization) {
            $recipients = $this->recipients($stage, $organization)
                ->reject(fn (User $user) => $alreadySent->contains($user->id.':'.$organization->id));

            if ($recipients->isEmpty()) {
                continue;
            }

            Notification::send($recipients, $stage === 'terlambat'
                ? new ReportOverdue($organization, $period)
                : new ReportReminder($organization, $period, $stage));
            $sentUsers += $recipients->count();
            $sentOrganizations++;
        }

        if ($sentUsers === 0) {
            $this->info($stage === 'terlambat' ? 'Tidak ada kelompok terlambat yang perlu diberi tahu.' : 'Tidak ada kelompok yang perlu diingatkan.');
        } else {
            $this->info(($stage === 'terlambat' ? 'Pemberitahuan terlambat ' : 'Pengingat laporan ')
                .Format::month($period)." dikirim ke {$sentUsers} akun dari {$sentOrganizations} kelompok.");
        }

        return self::SUCCESS;
    }

    /** Tahap yang jatuh hari ini (WIT) untuk periode itu, atau null. */
    public static function stageForToday(CarbonImmutable $period): ?string
    {
        $config = config('simker.reports');
        $today = now($config['timezone'])->toDateString();
        $deadline = MonthlyReport::deadlineFor($period);
        $opens = CarbonImmutable::parse($period->format('Y-m-01'), $config['timezone'])->addMonthNoOverflow();

        return match ($today) {
            $opens->setDay($config['reminder_first_day'])->toDateString() => 'awal',
            $deadline->subDays($config['reminder_days_before'])->toDateString() => 'akhir',
            $deadline->addDays($config['overdue_days_after'])->toDateString() => 'terlambat',
            default => null,
        };
    }

    private function organizations(string $stage, CarbonImmutable $period): Collection
    {
        $query = $stage === 'terlambat'
            ? Organization::overdueFor($period)->with('opds:id')
            : Organization::obligatedFor($period)->whereDoesntHave('monthlyReports', fn ($query) => $query
                ->whereDate('period', $period->format('Y-m-d'))
                ->where('status', '!=', ReportStatus::Draft));

        return $query
            ->with(['users' => fn ($query) => $query->where('role', Role::Kelompok)->where('is_active', true)])
            ->orderBy('name')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function recipients(string $stage, Organization $organization)
    {
        if ($stage !== 'terlambat') {
            return $organization->users;
        }

        $reviewers = $organization->opds->isEmpty()
            ? $this->reviewers()->where('role', Role::Admin)
            : $this->reviewers()->where('role', Role::Opd)->whereIn('opd_id', $organization->opds->modelKeys());

        return $organization->users->concat($reviewers)->values();
    }

    /** Akun Admin dan OPD aktif, dimuat sekali. */
    private function reviewers(): Collection
    {
        return $this->reviewerCache ??= User::query()
            ->whereIn('role', [Role::Admin, Role::Opd])
            ->where('is_active', true)
            ->get();
    }

    /** Pasangan "user_id:organization_id" yang sudah menerima tahap ini untuk periode itu. */
    private function alreadySent(string $stage, CarbonImmutable $period, array $organizationIds)
    {
        return DatabaseNotification::query()
            ->where('type', $stage === 'terlambat' ? ReportOverdue::class : ReportReminder::class)
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('data->period', $period->format('Y-m'))
            ->where('data->stage', $stage)
            ->get(['notifiable_id', 'data'])
            ->filter(fn (DatabaseNotification $notification) => in_array($notification->data['organization_id'] ?? null, $organizationIds, true))
            ->map(fn (DatabaseNotification $notification) => $notification->notifiable_id.':'.$notification->data['organization_id']);
    }
}
