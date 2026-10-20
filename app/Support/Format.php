<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Indonesian number and date formatting. NULL means "not filled in" and is never shown as 0.
 */
class Format
{
    public const EMPTY = '—';

    /** Teks nilai kosong di area aplikasi (x-admin.empty-value), menggantikan tanda pisah. */
    public const EMPTY_LABEL = 'Belum diisi';

    /**
     * Hasil format untuk ditampilkan di area aplikasi: nilai kosong (EMPTY, null, '') menjadi "Belum diisi" kecil ink-3,
     * selain itu teks apa adanya (tetap di-escape oleh Blade). Ekspor PDF/Excel tetap memakai EMPTY.
     */
    public static function orEmpty(mixed $formatted): Htmlable|string
    {
        // Sudah HTML aman (mis. hasil orEmpty sebelumnya): kembalikan apa adanya. Bila diubah ke string, Blade
        // meng-escape-nya dan kode <span> tampil mentah.
        if ($formatted instanceof Htmlable) {
            return $formatted;
        }

        if ($formatted === null || $formatted === '' || $formatted === self::EMPTY) {
            return new HtmlString('<span class="text-label font-normal text-ink-3">'.self::EMPTY_LABEL.'</span>');
        }

        return (string) $formatted;
    }

    /**
     * Versi teks polos orEmpty() untuk teks yang bukan HTML (atribut title, notifikasi, email, judul): "Belum diisi" tanpa
     * tag. Juga menerima hasil orEmpty(). Ekspor Excel/PDF tidak memakai ini: angka kosong tetap sel kosong / EMPTY.
     */
    public static function emptyText(mixed $formatted): string
    {
        if ($formatted instanceof Htmlable) {
            return trim(strip_tags($formatted->toHtml()));
        }

        return $formatted === null || $formatted === '' || $formatted === self::EMPTY ? self::EMPTY_LABEL : (string) $formatted;
    }

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

    /**
     * Rupiah ringkas tanpa tanda, KHUSUS kotak angka utama dasbor: "Rp 850 rb", "Rp 121,4 jt", "Rp 1,2 M".
     * Nilai lengkap (rupiah()) tetap ditampilkan di tooltip.
     */
    public static function rupiahShort(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        $amount = abs((float) $value);
        [$divisor, $suffix] = match (true) {
            $amount >= 1_000_000_000 => [1_000_000_000, ' M'],
            $amount >= 1_000_000 => [1_000_000, ' jt'],
            $amount >= 1_000 => [1_000, ' rb'],
            default => [1, ''],
        };
        $number = $divisor === 1 ? self::number($amount) : preg_replace('/,0$/', '', self::number($amount / $divisor, 1));

        return 'Rp '.$number.$suffix;
    }

    public static function fileSize(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? self::number(max(1, round($bytes / 1024))).' KB'
            : self::number($bytes / 1024 / 1024, 1).' MB';
    }

    /**
     * Nomor telepon untuk TAMPILAN (data tersimpan tidak diubah): dikelompokkan dengan spasi, bukan tanda hubung.
     * 0812-0000-1005 → "0812 0000 1005"; +62 812-0000-1005 → "+62 812 0000 1005". Nomor lain (mis. telepon kantor
     * berkode wilayah) hanya diganti pemisahnya menjadi spasi. Kosong → null.
     */
    public static function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        $group = fn (string $value) => trim(implode(' ', [substr($value, 0, 4), substr($value, 4, 4), substr($value, 8)]));

        return match (true) {
            str_starts_with($phone, '+62') && strlen($digits) >= 11 => '+62 '.substr($digits, 2, 3).' '.$group(substr($digits, 5)),
            str_starts_with($digits, '08') && strlen($digits) >= 10 && strlen($digits) <= 13 => $group($digits),
            default => trim(preg_replace('/\s+/', ' ', strtr($phone, ['-' => ' ', '.' => ' ']))),
        };
    }

    /** Tautan tel: dari nomor apa pun (angka dan + saja), atau null. */
    public static function telUrl(?string $phone): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $phone);

        return $digits === '' ? null : 'tel:'.$digits;
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
    /** Teks dengan kata kunci pencarian disorot (<mark>, sun pucat); teks lain di-escape. */
    public static function highlight(string $text, string $term): string
    {
        if ($term === '') {
            return e($text);
        }

        $parts = preg_split('/('.preg_quote($term, '/').')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        return collect($parts)->map(fn (string $part, int $i) => $i % 2 === 1
            ? '<mark class="rounded-sm bg-highlight text-ink">'.e($part).'</mark>'
            : e($part))->implode('');
    }

    public static function markdown(?string $text): ?string
    {
        return filled($text) ? Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : null;
    }

    /** Paragraf pertama sebagai teks biasa, untuk cuplikan dan meta description. */
    public static function excerpt(?string $markdown, int $limit = 300): ?string
    {
        if (blank($markdown)) {
            return null;
        }

        $first = preg_split('/\R\s*\R/', trim($markdown))[0];

        return Str::limit(trim(strip_tags(self::markdown($first))), $limit);
    }

    public static function date(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('j F Y');
    }

    /** "Kamis, 15 Oktober 2026" (nama hari + tanggal, Bahasa Indonesia). */
    public static function dayDate(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('l, j F Y');
    }

    /** "24 Sep 2026" (bulan disingkat, Bahasa Indonesia). */
    public static function dateShort(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('j M Y');
    }

    /** "Hari ini, 10.42", "Kemarin, 10.42", atau "9 Oktober 2026, 10.42". */
    public static function relativeDateTime(CarbonInterface|string|null $value): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        $date = Carbon::parse($value);

        return match (true) {
            $date->isToday() => 'Hari ini, '.$date->format('H.i'),
            $date->isYesterday() => 'Kemarin, '.$date->format('H.i'),
            default => self::dateTime($date),
        };
    }

    /** "9 Oktober 2026, 10.42" */
    public static function dateTime(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('j F Y, H.i');
    }

    /** Lama menunggu sejak suatu waktu, dalam hari kalender: "Hari ini", "3 hari". */
    public static function waitingSince(CarbonInterface|string|null $value): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        $days = (int) abs(Carbon::parse($value)->startOfDay()->diffInDays(now()->startOfDay()));

        return $days === 0 ? 'Hari ini' : $days.' hari';
    }

    /** Jumlah produksi/penjualan tanpa nol desimal yang tidak perlu: 12.50 → "12,5", 1250.00 → "1.250". */
    public static function quantity(int|float|string|null $value): string
    {
        return $value === null || $value === '' ? self::EMPTY : NumberInput::display($value, true);
    }

    /** Periode ringkas di tabel: "Sep 2026". */
    public static function monthShort(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('M Y');
    }

    /** Nama kampung di tabel tanpa awalan "Kampung " (kepala kolom sudah menjelaskan). */
    public static function village(?string $name): string
    {
        return $name === null || $name === '' ? self::EMPTY : (preg_replace('/^Kampung\s+/iu', '', $name) ?: $name);
    }

    public static function month(CarbonInterface|string|null $value): string
    {
        return $value === null ? self::EMPTY : Carbon::parse($value)->translatedFormat('F Y');
    }
}
