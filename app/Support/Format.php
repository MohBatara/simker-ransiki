<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Indonesian number and date formatting. NULL means "not filled in" and is never shown as 0.
 */
class Format
{
    public const EMPTY = '—';

    public static function number(int|float|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        return number_format((float) $value, $decimals, ',', '.');
    }

    public static function rupiah(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        $sign = $value < 0 ? '-' : '';

        return $sign.'Rp '.self::number(abs((float) $value));
    }

    public static function date(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('j F Y');
    }

    public static function month(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('F Y');
    }
}
