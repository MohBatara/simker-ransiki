<?php

namespace App\Models;

use App\Enums\ChangeAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris riwayat perubahan: satu kolom yang berubah, atau satu data yang ditambahkan/dihapus.
 * Ditulis App\Support\ChangeLogger dan tidak pernah diubah. Nilai lama/baru berupa teks siap tampil.
 */
#[Fillable(['organization_id', 'loggable_type', 'loggable_id', 'subject', 'action', 'field', 'old_value', 'new_value', 'user_id'])]
class ChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ChangeAction::class,
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
