<?php

namespace App\Enums;

/** Status verifikasi profil kelompok oleh OPD pembina atau Admin. */
enum ProfileStatus: string
{
    use HasOptions;

    case Unverified = 'unverified';
    case Verified = 'verified';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Profil belum pernah diverifikasi',
            self::Verified => 'Profil terverifikasi',
            self::NeedsReview => 'Perubahan menunggu verifikasi',
        };
    }
}
