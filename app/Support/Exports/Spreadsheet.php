<?php

namespace App\Support\Exports;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menulis file Excel (.xlsx) untuk diunduh. NULL ditulis sebagai sel kosong (bukan 0), dan teks yang
 * diawali = + - @ diberi apostrof agar tidak dijalankan sebagai rumus (formula injection).
 */
class Spreadsheet
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  list<array{name: string, header?: list<string>, rows: iterable<list<mixed>>}>  $sheets
     */
    public static function download(string $filename, array $sheets): BinaryFileResponse
    {
        return response()
            ->download(self::write($sheets), $filename, ['Content-Type' => self::MIME, 'X-Content-Type-Options' => 'nosniff'])
            ->deleteFileAfterSend();
    }

    /**
     * @param  list<array{name: string, header?: list<string>, rows: iterable<list<mixed>>}>  $sheets
     * @return string path file sementara
     */
    public static function write(array $sheets): string
    {
        $path = sys_get_temp_dir().'/simker-'.Str::uuid().'.xlsx';
        $writer = SimpleExcelWriter::create($path, configureWriter: function (Writer $writer) {
            $writer->getOptions()->DEFAULT_COLUMN_WIDTH = 22;
        })->noHeaderRow();
        $bold = (new Style)->setFontBold();

        foreach (array_values($sheets) as $index => $sheet) {
            if ($index > 0) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->nameCurrentSheet($sheet['name']);

            if (! empty($sheet['header'])) {
                $writer->addRow(Row::fromValues(array_map(self::cell(...), $sheet['header']), $bold));
            }
            foreach ($sheet['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_map(self::cell(...), array_values($row))));
            }
        }

        $writer->close();

        return $path;
    }

    public static function cell(mixed $value): int|float|string|null
    {
        return match (true) {
            $value === null || $value === '' => null,
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            is_int($value) || is_float($value) => $value,
            default => self::escape((string) $value),
        };
    }

    /** Apostrof di depan teks yang bisa dibaca Excel sebagai rumus. */
    public static function escape(string $text): string
    {
        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }
}
