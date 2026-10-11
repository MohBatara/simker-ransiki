<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pasangan kelompok yang dinyatakan Admin "Bukan data ganda". organization_id selalu id yang lebih kecil.
 */
#[Fillable(['organization_id', 'other_organization_id', 'user_id'])]
class DuplicateDismissal extends Model
{
    public const UPDATED_AT = null;

    /** Menyimpan pasangan (urutan id tidak penting); aman dipanggil berulang. */
    public static function dismiss(int $first, int $second, ?User $user): void
    {
        static::query()->insertOrIgnore([
            'organization_id' => min($first, $second),
            'other_organization_id' => max($first, $second),
            'user_id' => $user?->id,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, true> kunci "kecil-besar" untuk semua pasangan yang sudah ditandai */
    public static function keys(): array
    {
        return static::query()->get(['organization_id', 'other_organization_id'])
            ->mapWithKeys(fn (self $pair) => [static::key($pair->organization_id, $pair->other_organization_id) => true])
            ->all();
    }

    public static function key(int $first, int $second): string
    {
        return min($first, $second).'-'.max($first, $second);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
