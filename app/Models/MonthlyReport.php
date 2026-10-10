<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\Concerns\HasAttachments;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id', 'period', 'status', 'no_activity',
    'activity_summary', 'activity_count', 'achievements',
    'members_active', 'workforce_count', 'members_change',
    'income_sales', 'income_other', 'costs',
    'submitted_at', 'submitted_by', 'verified_at', 'verified_by',
])]
class MonthlyReport extends Model
{
    use HasAttachments, HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'no_activity' => false,
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'status' => ReportStatus::class,
            'no_activity' => 'boolean',
            'activity_count' => 'integer',
            'members_active' => 'integer',
            'workforce_count' => 'integer',
            'members_change' => 'integer',
            'income_sales' => 'integer',
            'income_other' => 'integer',
            'costs' => 'integer',
            'profit' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MonthlyReport $report) {
            $report->period = $report->period?->startOfMonth();
            $report->profit = static::calculateProfit($report->income_sales, $report->income_other, $report->costs);
        });
    }

    /**
     * NULL bila salah satu komponen belum diisi; nilai 0 tetap dihitung sebagai nol.
     */
    public static function calculateProfit(?int $incomeSales, ?int $incomeOther, ?int $costs): ?int
    {
        if ($incomeSales === null || $incomeOther === null || $costs === null) {
            return null;
        }

        return $incomeSales + $incomeOther - $costs;
    }

    /**
     * Tenggat laporan suatu periode: tanggal `simker.reports.deadline_day` bulan berikutnya, 23.59 WIT.
     */
    public static function deadlineFor(CarbonInterface $period): CarbonImmutable
    {
        $timezone = config('simker.reports.timezone');
        $next = CarbonImmutable::parse($period->format('Y-m-01'), $timezone)->addMonthNoOverflow();
        $day = min(config('simker.reports.deadline_day'), $next->daysInMonth);

        return $next->setDay($day)->endOfDay();
    }

    public static function isPastDeadline(CarbonInterface $period): bool
    {
        return now()->gt(static::deadlineFor($period));
    }

    public function deadline(): CarbonImmutable
    {
        return static::deadlineFor($this->period);
    }

    /**
     * Terlambat: lewat tenggat dan belum pernah diajukan. Laporan yang diajukan tepat waktu lalu
     * diminta revisi tidak dihitung terlambat, karena submitted_at menyimpan pengajuan pertama.
     */
    public function isOverdue(): bool
    {
        return $this->submitted_at === null && static::isPastDeadline($this->period);
    }

    /** Informasi saja: pengajuan pertama melewati tenggat. */
    public function wasSubmittedLate(): bool
    {
        return $this->submitted_at !== null && $this->submitted_at->gt($this->deadline());
    }

    /** Laporan bulan berjalan baru bisa diajukan mulai tanggal 1 bulan berikutnya. */
    public function submissionOpensOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->period->format('Y-m-01'), config('simker.reports.timezone'))->addMonthNoOverflow();
    }

    public function submissionIsOpen(): bool
    {
        return now()->gte($this->submissionOpensOn());
    }

    /** Relasi untuk halaman detail (shared.reports.show). */
    public function loadDetail(): static
    {
        return $this->load([
            'organization', 'items', 'issues', 'reviews.user',
            'reviews' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'latestRevisionRequest.user.opd',
        ]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ReportIssue::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class);
    }

    /** Permintaan revisi terakhir; catatannya ditampilkan di atas form dan detail. */
    public function latestRevisionRequest(): HasOne
    {
        return $this->hasOne(ReportReview::class)->ofMany(['id' => 'max'], function ($query) {
            $query->where('action', ReviewAction::RevisionRequested);
        });
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
