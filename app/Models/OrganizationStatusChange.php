<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'from_status', 'to_status', 'reason'])]
class OrganizationStatusChange extends Model
{
    // Riwayat audit: baris tidak pernah diubah setelah dibuat.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => OrganizationStatus::class,
            'to_status' => OrganizationStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
