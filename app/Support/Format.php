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

    public static function fileSize(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? self::number(max(1, round($bytes / 1024))).' KB'
            : self::number($bytes / 1024 / 1024, 1).' MB';
    }

    /** Tautan WhatsApp dari nomor Indonesia: 0812… atau +62 812… menjadi https://wa.me/62812…. */
    public static function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        $digits = match (true) {
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };

        return 'https://wa.me/'.$digits;
    }

    /**
     * Markdown terbatas dari Konten Website: HTML mentah dibuang, tautan javascript: dan sejenisnya ditolak.
     */
    public static function markdown(?string $text): ?string
    {
        return filled($text) ? \Illuminate\Support\Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : null;
    }

    /** Paragraf pertama sebagai teks biasa, untuk cuplikan dan meta description. */
    public static function excerpt(?string $markdown, int $limit = 300): ?string
    {
        if (blank($markdown)) {
            return null;
        }

        $first = preg_split('/\R\s*\R/', trim($markdown))[0];

        return \Illuminate\Support\Str::limit(trim(strip_tags(self::markdown($first))), $limit);
    }

    public static function date(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('j F Y');
    }

    /** "9 Oktober 2026, 10.42" */
    public static function dateTime(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('j F Y, H.i');
    }

    /** Jumlah produksi/penjualan tanpa nol desimal yang tidak perlu: 12.50 → "12,5", 1250.00 → "1.250". */
    public static function quantity(int|float|string|null $value): string
    {
        return $value === null || $value === '' ? self::EMPTY : NumberInput::display($value, true);
    }

    public static function month(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('F Y');
    }
}
