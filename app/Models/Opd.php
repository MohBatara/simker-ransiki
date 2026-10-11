<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'short_name', 'description'])]
class Opd extends Model
{
    use HasFactory;

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_opd');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Tindak lanjut masalah & kebutuhan dengan OPD ini sebagai penanggung jawab (membuat OPD "dipakai"). */
    public function issueFollowUps(): HasMany
    {
        return $this->hasMany(IssueFollowUp::class);
    }
}
