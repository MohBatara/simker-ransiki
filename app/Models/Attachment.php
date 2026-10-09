<?php

namespace App\Models;

use App\Enums\AttachmentVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'visibility', 'uploaded_by'])]
class Attachment extends Model
{
    use HasFactory;

    protected $attributes = [
        'visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visibility' => AttachmentVisibility::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
