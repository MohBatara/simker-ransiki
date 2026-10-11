<?php

namespace App\Support\Imports;

use App\Models\BusinessSector;
use App\Models\InstitutionType;
use App\Models\Opd;
use App\Models\Village;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

/**
 * Membaca dan memeriksa file impor kelompok (.xlsx atau .csv) tanpa menyimpan apa pun. Setiap baris diberi
 * status Siap / Peringatan / Gagal beserta alasannya. Data master dicocokkan tanpa peduli huruf besar-kecil
 * dan spasi berlebih. Angka kosong tetap NULL.
 */
class OrganizationImport
{
    public const MAX_ROWS = 500;

    public const READY = 'ready';

    public const WARNING = 'warning';

    public const FAILED = 'failed';

    /** Kolom file: kunci => [judul kolom, wajib, keterangan di sheet Petunjuk]. */
    public const COLUMNS = [
        'name' => ['Nama kelompok', true, 'Nama lengkap kelompok.'],
        'type' => ['Jenis kelembagaan', true, 'Salah satu jenis kelembagaan di bawah.'],
        'village' => ['Kampung', true, 'Salah satu kampung di bawah.'],
        'sectors' => ['Bidang usaha', true, 'Satu atau lebih bidang usaha di bawah. Pisahkan dengan titik koma (;).'],
        'opds' => ['OPD terkait', false, 'Nama atau singkatan OPD di bawah. Pisahkan dengan titik koma (;). Boleh kosong.'],
        'reporting_starts_on' => ['Mulai wajib lapor', false, 'Bulan pertama wajib lapor, format TTTT-BB (mis. 2026-09). Kosong = bulan ini.'],
        'leader_name' => ['Nama ketua', false, ''],
        'phone' => ['Telepon', false, 'Angka, spasi, +, -, atau tanda kurung. Format sel sebagai Teks agar angka 0 di depan tidak hilang.'],
        'email' => ['Email', false, ''],
        'address' => ['Alamat', false, ''],
        'founded_year' => ['Tahun berdiri', false, 'Empat angka, mis. 2019.'],
        'members_count' => ['Jumlah anggota', false, 'Angka bulat. Kosongkan bila belum diketahui (jangan diisi 0).'],
        'workforce_count' => ['Jumlah tenaga kerja', false, 'Angka bulat. Kosongkan bila belum diketahui.'],
        'description' => ['Deskripsi', false, ''],
    ];

    /**
     * @param  list<array{line: int, values: array<string, ?string>, data: ?array, status: string, messages: list<string>}>  $rows
     */
    private function __construct(public readonly array $rows) {}

    /** Judul kolom di template; kolom wajib diberi tanda "(wajib)". */
    public static function headers(): array
    {
        return array_map(fn (array $column) => $column[0].($column[1] ? ' (wajib)' : ''), array_values(self::COLUMNS));
    }

    /**
     * @param  string  $type  'xlsx' atau 'csv'
     *
     * @throws ImportException bila file tidak bisa dibaca, kolom wajib tidak ada, atau baris terlalu banyak
     */
    public static function read(string $path, string $type): self
    {
        try {
            $reader = SimpleExcelReader::create($path, $type)->noHeaderRow()->preserveEmptyRows();

            if ($type === 'csv') {
                $reader->useDelimiter(self::csvDelimiter($path));
            } elseif ($reader->hasSheet('Data')) {
                $reader->fromSheetName('Data');
            }

            $lines = [];
            $header = null;
            foreach ($reader->getRows() as $index => $cells) {
                if ($header === null) {
                    $header = self::mapHeader($cells);

                    continue;
                }

                $values = collect($header)->map(fn (int $column) => self::text($cells[$column] ?? null))->all();
                if (array_filter($values, fn ($value) => $value !== null) === []) {
                    continue;
                }
                if (count($lines) === self::MAX_ROWS) {
                    throw new ImportException('File berisi lebih dari '.self::MAX_ROWS.' baris data. Bagi menjadi beberapa file.');
                }

                $lines[] = ['line' => $index + 1, 'values' => $values];
            }
            $reader->close();
        } catch (ImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ImportException('File tidak bisa dibaca. Simpan ulang sebagai .xlsx atau .csv, sebaiknya dari template.');
        }

        if ($header === null) {
            throw new ImportException('File kosong. Isi data mulai baris kedua, di bawah judul kolom.');
        }
        if ($lines === []) {
            throw new ImportException('Belum ada baris data. Isi data mulai baris kedua, di bawah judul kolom.');
        }

        return new self((new RowChecker)->check($lines));
    }

    /** @return array{ready: int, warning: int, failed: int} */
    public function counts(): array
    {
        $counts = collect($this->rows)->countBy('status');

        return [self::READY => $counts[self::READY] ?? 0, self::WARNING => $counts[self::WARNING] ?? 0, self::FAILED => $counts[self::FAILED] ?? 0];
    }

    /** Huruf kecil, spasi berlebih dibuang: dipakai untuk mencocokkan judul kolom dan data master. */
    public static function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
    }

    /** @return array<string, int> kunci kolom => indeks kolom di file */
    private static function mapHeader(array $cells): array
    {
        $labels = collect(self::COLUMNS)->mapWithKeys(fn (array $column, string $key) => [self::normalize($column[0]) => $key]);
        $header = [];

        foreach (array_values($cells) as $index => $cell) {
            $label = self::normalize(preg_replace('/\(wajib\)|\*/iu', '', (string) self::text($cell)));
            if (($key = $labels[$label] ?? null) && ! isset($header[$key])) {
                $header[$key] = $index;
            }
        }

        $missing = collect(self::COLUMNS)->filter(fn (array $column, string $key) => $column[1] && ! isset($header[$key]))->map(fn (array $column) => $column[0]);
        if ($missing->isNotEmpty()) {
            throw new ImportException('Kolom wajib tidak ditemukan: '.$missing->join(', ').'. Pakai judul kolom dari template.');
        }

        return $header;
    }

    /** Nilai sel sebagai teks; sel kosong menjadi NULL. Angka dan tanggal dari Excel ikut diubah. */
    public static function text(mixed $value): ?string
    {
        $text = match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->format('Y-m-d'),
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            is_float($value) && floor($value) === $value => (string) (int) $value,
            default => (string) $value,
        };

        $text = $text === null ? null : trim(preg_replace('/^\x{FEFF}/u', '', $text));

        return $text === '' ? null : $text;
    }

    /** Excel berbahasa Indonesia sering menyimpan CSV dengan titik koma. */
    private static function csvDelimiter(string $path): string
    {
        $first = (string) fgets($handle = fopen($path, 'r'));
        fclose($handle);

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }

    /**
     * Daftar nilai yang valid untuk sheet Petunjuk.
     *
     * @return array<string, list<string>>
     */
    public static function validValues(): array
    {
        return [
            'Jenis kelembagaan' => InstitutionType::orderBy('name')->pluck('name')->all(),
            'Kampung' => Village::orderBy('name')->pluck('name')->all(),
            'Bidang usaha' => BusinessSector::orderBy('name')->pluck('name')->all(),
            'OPD terkait' => Opd::orderBy('name')->get()->map(fn (Opd $opd) => $opd->short_name ? "{$opd->name} ({$opd->short_name})" : $opd->name)->all(),
        ];
    }
}
