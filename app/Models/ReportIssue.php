<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\IssueKind;
use App\Enums\ProblemCategory;
use App\Enums\ReportStatus;
use App\Enums\SupportCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kind', 'category', 'description'])]
class ReportIssue extends Model
{
    protected $attributes = [
        'follow_up_status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'kind' => IssueKind::class,
            'follow_up_status' => FollowUpStatus::class,
            'follow_up_at' => 'datetime',
        ];
    }

    /**
     * Kategori bergantung pada jenisnya: ProblemCategory untuk masalah, SupportCategory untuk kebutuhan.
     */
    public function categoryLabel(): string
    {
        $category = match ($this->kind) {
            IssueKind::Problem => ProblemCategory::tryFrom($this->category),
            IssueKind::Support => SupportCategory::tryFrom($this->category),
        };

        return $category?->label() ?? $this->category;
    }

    /**
     * Masalah/kebutuhan yang boleh dilihat peninjau: dari laporan diajukan atau terverifikasi milik
     * kelompok yang terlihat oleh pengguna. Draf dan laporan yang sedang direvisi tidak ikut.
     */
    public function scopeReviewableBy(Builder $query, User $user): void
    {
        $query->whereHas('monthlyReport', fn ($query) => $query
            ->visibleTo($user)
            ->whereIn('status', [ReportStatus::Submitted, ReportStatus::Verified]));
    }

    /**
     * Belum ditindaklanjuti lebih dari N hari: status Baru/Ditinjau dan tidak berubah sejak N hari lalu
     * (dihitung dari perubahan status terakhir, atau dari pengajuan pertama laporannya).
     */
    public function scopeStale(Builder $query, int $days): void
    {
        $limit = now()->subDays($days);

        $query->whereIn('report_issues.follow_up_status', FollowUpStatus::pending())
            ->where(fn ($query) => $query
                ->where('report_issues.follow_up_at', '<', $limit)
                ->orWhere(fn ($query) => $query->whereNull('report_issues.follow_up_at')
                    ->whereHas('monthlyReport', fn ($query) => $query->where('submitted_at', '<', $limit))));
    }

    /**
     * Filter: jenis (kind), kategori, status tindak lanjut, kampung, periode (Y-m), OPD pembina, dan
     * lama=N (belum ditindaklanjuti lebih dari N hari; tautan dari kartu dasbor).
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? null;

        $query
            ->when(IssueKind::tryFrom((string) ($filters['kind'] ?? '')), fn ($query, $kind) => $query->where('kind', $kind))
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category', (string) $filters['category']))
            ->when(FollowUpStatus::tryFrom((string) ($filters['status'] ?? '')), fn ($query, $status) => $query->where('follow_up_status', $status))
            ->when((int) ($filters['village'] ?? 0), fn ($query, $village) => $query
                ->whereHas('monthlyReport.organization', fn ($query) => $query->where('village_id', $village)))
            ->when((int) ($filters['lama'] ?? 0), fn ($query, $days) => $query->stale($days))
            ->when(is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period), fn ($query) => $query
                ->whereHas('monthlyReport', fn ($query) => $query->whereDate('period', $period.'-01')))
            ->when((int) ($filters['opd'] ?? 0), fn ($query, $opd) => $query
                ->whereHas('monthlyReport', fn ($query) => $query->filter(['opd' => $opd])));
    }

    /** Periode laporan induk, untuk orderBy daftar (terbaru dulu) tanpa join. */
    public static function periodSubquery(): Builder
    {
        return MonthlyReport::select('period')->whereColumn('monthly_reports.id', 'report_issues.monthly_report_id');
    }

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    /** Riwayat tindak lanjut, terbaru dulu. */
    public function followUps(): HasMany
    {
        return $this->hasMany(IssueFollowUp::class)->latest('id');
    }

    /** OPD penanggung jawab terkini. */
    public function followUpOpd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'follow_up_opd_id');
    }
}
