<?php

namespace App\Enums;

enum ReviewAction: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case RevisionRequested = 'revision_requested';
    case Verified = 'verified';
    case Comment = 'comment';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::RevisionRequested => 'Diminta revisi',
            self::Verified => 'Diverifikasi',
            self::Comment => 'Komentar',
        };
    }
}
