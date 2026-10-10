<?php

namespace App\Support\Dashboards;

use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Periode di dasbor. "Bulan wajib lapor" = bulan lalu (bulan terakhir yang sudah selesai);
 * bulan berjalan belum bisa diajukan sehingga tidak bisa dipilih.
 */
class ReportingPeriod
{
    public static function due(): CarbonImmutable
    {
        return now()->toImmutable()->startOfMonth()->subMonthNoOverflow();
    }

    /** Periode dari ?periode=Y-m bila sah dan dalam rentang; selain itu bulan wajib lapor. */
    public static function resolve(mixed $input, ?CarbonImmutable $earliest): CarbonImmutable
    {
        $due = self::due();

        if (! is_string($input) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input)) {
            return $due;
        }

        $period = CarbonImmutable::createFromFormat('!Y-m', $input);

        return $period->gt($due) || ($earliest && $period->lt($earliest)) ? $due : $period;
    }

    /** @return array<string, string> Y-m => "September 2026", terbaru dulu */
    public static function options(?CarbonImmutable $earliest): array
    {
        $options = [];
        $earliest ??= self::due();

        for ($period = self::due(); $period->gte($earliest); $period = $period->subMonthNoOverflow()) {
            $options[$period->format('Y-m')] = Format::month($period);
        }

        return $options;
    }

    /** Teks jarak ke tenggat: "hari ini", "6 hari lagi", "lewat 3 hari". */
    public static function deadlineDistance(CarbonImmutable $deadline): string
    {
        $days = (int) now()->startOfDay()->diffInDays($deadline->startOfDay(), false);

        return match (true) {
            $days === 0 => 'hari ini',
            $days > 0 => $days.' hari lagi',
            default => 'lewat '.abs($days).' hari',
        };
    }
}
