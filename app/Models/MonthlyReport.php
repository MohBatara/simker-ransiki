<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
