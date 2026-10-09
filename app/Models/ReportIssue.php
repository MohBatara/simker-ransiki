<?php

namespace App\Models;

use App\Enums\IssueKind;
use App\Enums\ProblemCategory;
use App\Enums\SupportCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }
}
