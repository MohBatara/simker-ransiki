<?php

namespace App\Observers;

use App\Models\Organization;
use App\Support\ChangeLogger;
use Illuminate\Database\Eloquent\Model;

/** Mencatat riwayat perubahan Organization, Product, dan License ke change_logs. */
class ChangeLogObserver
{
    public function created(Model $model): void
    {
        ChangeLogger::created($model);
    }

    public function updated(Model $model): void
    {
        ChangeLogger::updated($model);
    }

    public function deleted(Model $model): void
    {
        // Riwayat kelompok ikut terhapus bersama kelompoknya (FK cascade).
        if (! $model instanceof Organization) {
            ChangeLogger::deleted($model);
        }
    }
}
