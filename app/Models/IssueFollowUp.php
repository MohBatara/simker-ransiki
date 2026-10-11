<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat tindak lanjut satu masalah/kebutuhan. Baris tidak pernah diubah setelah dibuat. */
#[Fillable(['user_id', 'opd_id', 'from_status', 'to_status', 'note', 'acted_on'])]
class IssueFollowUp extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => FollowUpStatus::class,
            'to_status' => FollowUpStatus::class,
            'acted_on' => 'date',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(ReportIssue::class, 'report_issue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** OPD penanggung jawab tindakan ini. */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
