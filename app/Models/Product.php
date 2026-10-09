<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'description', 'unit', 'capacity', 'is_featured', 'is_public', 'photo_path'])]
class Product extends Model
{
    use HasAttachments, HasFactory;

    /** Foto produk tampil di halaman publik, jadi disimpan di disk public. */
    public const PHOTO_DISK = 'public';

    protected $attributes = [
        'is_featured' => false,
        'is_public' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Product $product) {
            if ($product->photo_path) {
                Storage::disk(self::PHOTO_DISK)->delete($product->photo_path);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reportItems(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }
}
