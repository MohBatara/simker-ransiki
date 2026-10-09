<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
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

    /** Foto disimpan privat dan dibuka lewat route /foto-produk/{id} yang memeriksa hak akses. */
    public const PHOTO_DISK = 'local';

    public const THUMBNAIL_SUFFIX = '-kecil';

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
        static::deleted(fn (Product $product) => $product->deletePhotoFiles());
    }

    public function thumbnailPath(): ?string
    {
        return $this->photo_path ? preg_replace('/\.jpg$/', self::THUMBNAIL_SUFFIX.'.jpg', $this->photo_path) : null;
    }

    public function deletePhotoFiles(): void
    {
        if ($this->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete([$this->photo_path, $this->thumbnailPath()]);
        }
    }

    /** Parameter v berubah setiap foto diganti, sehingga cache browser untuk foto lama tidak dipakai. */
    public function photoUrl(bool $thumbnail = false): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return route('product-photos.show', array_filter([
            'product' => $this->id,
            'ukuran' => $thumbnail ? 'kecil' : null,
            'v' => substr(md5($this->photo_path), 0, 8),
        ]));
    }

    /** Foto boleh dilihat tanpa login hanya bila produk dan kelompoknya tampil di direktori publik. */
    public function isPubliclyVisible(): bool
    {
        $organization = $this->organization()->first(['id', 'status', 'is_public']);

        return $this->is_public
            && $organization?->is_public
            && $organization->status === OrganizationStatus::Active;
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
