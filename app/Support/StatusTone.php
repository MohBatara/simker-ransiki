<?php

namespace App\Support;

use App\Enums\FollowUpStatus;
use App\Enums\LegalStatus;
use App\Enums\LicenseStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PostStatus;
use App\Enums\ProfileStatus;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use BackedEnum;

/**
 * SATU peta warna status untuk x-admin.badge-status: success (terverifikasi, aktif, terbit), warning (menunggu,
 * diproses), info (tindak lanjut berjalan), danger (perlu revisi, perlu perbaikan, terlambat, ditutup, tidak dapat),
 * neutral (draf, belum ada, tidak aktif). Status baru wajib ditambahkan di sini.
 */
class StatusTone
{
    public const TONES = ['success', 'warning', 'info', 'danger', 'neutral'];

    public static function of(BackedEnum $status): string
    {
        return match ($status) {
            ReportStatus::Verified, ReportingState::Verified, OrganizationStatus::Active, LicenseStatus::Issued,
            ProfileStatus::Verified, LegalStatus::Registered, FollowUpStatus::Done, PostStatus::Published => 'success',

            ReportStatus::Submitted, ReportingState::Submitted, LicenseStatus::InProcess, LegalStatus::InProcess,
            ProfileStatus::NeedsReview => 'warning',

            FollowUpStatus::Reviewed, FollowUpStatus::Planned, FollowUpStatus::InProgress => 'info',

            ReportStatus::Revision, ReportingState::Revision, LicenseStatus::NeedsFix,
            ReportingState::Overdue, OrganizationStatus::Closed, FollowUpStatus::Unable => 'danger',

            default => 'neutral',
        };
    }
}
