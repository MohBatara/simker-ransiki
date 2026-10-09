<?php

namespace App\Models;

use App\Enums\ReviewAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'note'])]
class ReportReview extends Model
{
    // Riwayat audit: baris tidak pernah diubah setelah dibuat.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ReviewAction::class,
        ];
    }

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
