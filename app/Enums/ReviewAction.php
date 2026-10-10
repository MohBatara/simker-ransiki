<?php

namespace App\Enums;

enum ReviewAction: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case RevisionRequested = 'revision_requested';
    case Verified = 'verified';
    case Comment = 'comment';
    // Admin membuka kembali laporan terverifikasi untuk direvisi.
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::RevisionRequested => 'Diminta revisi',
            self::Verified => 'Diverifikasi',
            self::Comment => 'Komentar',
            self::Reopened => 'Dibuka kembali untuk revisi',
        };
    }
}
