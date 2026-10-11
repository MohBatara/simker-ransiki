<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Models\Concerns\HasAttachments;
use App\Observers\ChangeLogObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'number', 'status', 'submitted_on', 'issued_on', 'expires_on', 'notes'])]
#[ObservedBy(ChangeLogObserver::class)]
class License extends Model
{
    use HasAttachments, HasFactory;

    protected $attributes = [
        'status' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'submitted_on' => 'date',
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
