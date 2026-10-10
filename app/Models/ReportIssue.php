<?php

namespace App\Models;

use App\Enums\IssueKind;
use App\Enums\ProblemCategory;
use App\Enums\ReportStatus;
use App\Enums\SupportCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kind', 'category', 'description'])]
class ReportIssue extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => IssueKind::class,
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

    /** Filter: jenis (kind), kategori, periode (Y-m), dan OPD pembina. */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? null;

        $query
            ->when(IssueKind::tryFrom((string) ($filters['kind'] ?? '')), fn ($query, $kind) => $query->where('kind', $kind))
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category', (string) $filters['category']))
            ->when(is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period), fn ($query) => $query
                ->whereHas('monthlyReport', fn ($query) => $query->whereDate('period', $period.'-01')))
            ->when((int) ($filters['opd'] ?? 0), fn ($query, $opd) => $query
                ->whereHas('monthlyReport', fn ($query) => $query->filter(['opd' => $opd])));
    }

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }
}
