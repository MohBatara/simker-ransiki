<?php

namespace App\Enums;

enum ReportStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Revision = 'revision';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Revision => 'Perlu revisi',
            self::Verified => 'Terverifikasi',
        };
    }

    /** Label di daftar Admin/OPD: draf belum bisa dibuka isinya, jadi dijelaskan. */
    public function reviewerLabel(): string
    {
        return $this === self::Draft ? 'Draf, belum diajukan' : $this->label();
    }

    /** @return array<string, string> */
    public static function reviewerOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->reviewerLabel()])->all();
    }

    /** Hanya draf dan laporan yang diminta revisi yang bisa diubah kelompok. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Revision], true);
    }

    /** Tone untuk komponen x-badge: ok, wait, fix, idle. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'idle',
            self::Submitted => 'wait',
            self::Revision => 'fix',
            self::Verified => 'ok',
        };
    }
}
