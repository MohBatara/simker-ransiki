<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class BusinessSector extends Model
{
    use HasFactory;

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_sector');
    }
}
