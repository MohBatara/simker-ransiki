<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public static function bootHasAttachments(): void
    {
        // Relasi polimorfik tidak punya FK cascade. Dijalankan setelah induk benar-benar terhapus,
        // lewat Eloquent agar event Attachment ikut menghapus file fisiknya.
        static::deleted(function (Model $model) {
            $model->attachments()->get()->each->delete();
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
