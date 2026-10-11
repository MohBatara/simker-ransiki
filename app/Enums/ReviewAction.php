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
    // OPD/Admin mengisi atau mengubah laporan atas nama kelompok (dicatat sekali per akun per laporan).
    case Entered = 'entered';

    /** Tindakan yang membuat akun tidak boleh memverifikasi laporan itu (pemisahan tugas). */
    public static function handling(): array
    {
        return [self::Entered, self::Submitted];
    }

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::RevisionRequested => 'Diminta revisi',
            self::Verified => 'Diverifikasi',
            self::Comment => 'Komentar',
            self::Reopened => 'Dibuka kembali untuk revisi',
            self::Entered => 'Diisi atas nama kelompok',
        };
    }
}
