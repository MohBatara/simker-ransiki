<?php

namespace App\Enums;

/** Status tindak lanjut satu masalah/kebutuhan dari laporan bulanan. */
enum FollowUpStatus: string
{
    use HasOptions;

    case New = 'new';
    case Reviewed = 'reviewed';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Unable = 'unable';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Reviewed => 'Ditinjau',
            self::Planned => 'Direncanakan',
            self::InProgress => 'Sedang ditindaklanjuti',
            self::Done => 'Selesai',
            self::Unable => 'Tidak dapat ditindaklanjuti',
        };
    }

    /** Tone untuk komponen x-badge: ok, wait, fix, idle. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::New, self::Unable => 'idle',
            self::Reviewed, self::Planned, self::InProgress => 'wait',
            self::Done => 'ok',
        };
    }

    /** Belum benar-benar ditindaklanjuti (dasar kartu "Kebutuhan belum ditindaklanjuti"). */
    public static function pending(): array
    {
        return [self::New, self::Reviewed];
    }
}
