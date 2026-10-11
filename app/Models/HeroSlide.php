<?php

namespace App\Models;

use App\Enums\HeroFocus;
use App\Http\Controllers\Publik\SiteImageController;
use App\Support\ImageVariants;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto hero beranda yang diatur Admin (Konten Website > Beranda). Maksimal MAX slide.
 */
#[Fillable(['caption', 'alt_text', 'focus', 'is_active'])]
class HeroSlide extends Model
{
    use HasFactory;

    public const MAX = 3;

    protected function casts(): array
    {
        return [
            'focus' => HeroFocus::class,
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (HeroSlide $slide) => ImageVariants::delete(SiteImageController::DISK, $slide->photo_path, ImageVariants::HERO));
    }

    public function scopeShown(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id')->limit(self::MAX);
    }
}
