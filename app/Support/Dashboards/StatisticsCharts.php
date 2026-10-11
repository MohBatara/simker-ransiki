<?php

namespace App\Support\Dashboards;

use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Menyusun data dari App\Support\Dashboards\Statistics menjadi atribut komponen x-chart: label, dataset,
 * tabel berisi angka yang sama ("Lihat tabel"), teks aria, catatan kaki, dan pesan kosong. Nilai null tetap null.
 */
class StatisticsCharts
{
    public const PROVISIONAL_NOTE = 'Garis putus-putus = data bulan itu belum lengkap.';

    public static function incomeTrend(Collection $trend, bool $coverage = true): array
    {
        $notes = fn (array $row) => array_values(array_filter([
            $coverage ? 'Diisi '.$row['filled'].' dari '.$row['obligated'].' kelompok' : null,
            ! $row['provisional'] ? null : ($coverage
                ? 'Sementara: baru '.$row['reports'].' dari '.$row['obligated'].' kelompok terverifikasi'
                : 'Sementara: data bulan ini belum lengkap'),
        ]));
        $hasProvisional = $trend->contains(fn (array $row) => $row['provisional'] && $row['total'] !== null);

        return [
            'type' => 'line',
            'format' => 'rupiah',
            'labels' => self::monthLabels($trend),
            'datasets' => [[
                'label' => 'Pendapatan penjualan',
                'tone' => 'brand',
                'data' => $trend->pluck('total')->all(),
                'provisional' => $trend->pluck('provisional')->all(),
                'notes' => $trend->map($notes)->all(),
            ]],
            'table' => [
                'columns' => [...($coverage ? ['Bulan', 'Pendapatan penjualan', 'Cakupan'] : ['Bulan', 'Pendapatan penjualan']), 'Keterangan'],
                'numeric' => [1],
                'rows' => $trend->map(fn (array $row) => [
                    Format::month($row['period']),
                    Format::rupiah($row['total']),
                    ...($coverage ? ['Diisi '.$row['filled'].' dari '.$row['obligated'].' kelompok'] : []),
                    $row['provisional'] ? 'Sementara' : '',
                ])->all(),
            ],
            'summary' => 'Pendapatan penjualan per bulan. '.$trend->map(fn (array $row) => Format::month($row['period']).': '
                .($row['total'] === null ? 'tidak ada data' : Format::rupiah($row['total']))
                .($row['provisional'] ? ' (sementara)' : ''))->join('; ').'.',
            'footnote' => $hasProvisional ? self::PROVISIONAL_NOTE : null,
            'empty' => $trend->whereNotNull('total')->isEmpty()
                ? 'Belum ada laporan terverifikasi yang mengisi pendapatan penjualan pada rentang ini.'
                : null,
        ];
    }

    /**
     * Kepatuhan pelaporan, ditumpuk dari bawah dengan urutan tetap. Legenda di atas grafik memuat jumlah
     * bulan terakhir, sehingga segmen tetap bisa dibedakan tanpa warna (urutan + legenda + tabel).
     */
    public static function compliance(Collection $compliance): array
    {
        $series = [
            'verified' => ['Terverifikasi', 'verified'],
            'submitted' => ['Menunggu verifikasi', 'waiting'],
            'revision' => ['Perlu revisi', 'revision'],
            'overdue' => ['Terlambat', 'overdue'],
            'pending' => ['Belum diajukan', 'pending'],
        ];
        $last = $compliance->last();

        return [
            'type' => 'bar-stacked',
            'format' => 'number',
            'labels' => self::monthLabels($compliance),
            'legend' => ['position' => 'top', 'title' => $last ? 'Jumlah '.Format::month($last['period']).':' : null, 'lastValue' => true],
            'datasets' => collect($series)->map(fn (array $definition, string $key) => [
                'label' => $definition[0],
                'tone' => $definition[1],
                'data' => $compliance->pluck($key)->all(),
            ])->values()->all(),
            'table' => [
                'columns' => ['Bulan', ...array_column($series, 0), 'Wajib lapor'],
                'numeric' => [1, 2, 3, 4, 5, 6],
                'rows' => $compliance->map(fn (array $row) => [
                    Format::month($row['period']),
                    ...collect(array_keys($series))->map(fn (string $key) => Format::number($row[$key]))->all(),
                    Format::number($row['obligated']),
                ])->all(),
            ],
            'summary' => 'Kepatuhan pelaporan per bulan. '.$compliance->map(fn (array $row) => Format::month($row['period']).': '
                .collect($series)->map(fn (array $definition, string $key) => $row[$key].' '.mb_strtolower($definition[0]))->join(', '))->join('; ').'.',
            'empty' => $compliance->sum('obligated') === 0 ? 'Belum ada kelompok wajib lapor pada rentang ini.' : null,
        ];
    }

    public static function incomeBySector(Collection $sectors): array
    {
        return [
            'type' => 'bar-horizontal',
            'format' => 'rupiah',
            'labels' => $sectors->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Pendapatan penjualan',
                'tone' => 'brand',
                'data' => $sectors->pluck('total')->all(),
                'notes' => $sectors->map(fn (array $row) => ['Dari '.$row['organizations'].' kelompok'])->all(),
            ]],
            'table' => [
                'columns' => ['Bidang usaha', 'Pendapatan penjualan', 'Kelompok'],
                'numeric' => [1, 2],
                'rows' => $sectors->map(fn (array $row) => [$row['label'], Format::rupiah($row['total']), Format::number($row['organizations'])])->all(),
            ],
            'summary' => 'Pendapatan penjualan per bidang usaha. '.$sectors->map(fn (array $row) => $row['label'].': '.Format::rupiah($row['total']))->join('; ').'.',
            'empty' => $sectors->whereNotNull('total')->isEmpty()
                ? 'Belum ada laporan terverifikasi yang mengisi pendapatan penjualan pada rentang ini.'
                : null,
            'height' => self::horizontalHeight($sectors->count()),
        ];
    }

    public static function issues(Collection $rows, string $title): array
    {
        return [
            'type' => 'bar-horizontal',
            'format' => 'number',
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Jumlah laporan',
                'tone' => 'brand',
                'data' => $rows->pluck('reports')->all(),
                'notes' => $rows->map(fn (array $row) => ['Dari '.$row['organizations'].' kelompok'])->all(),
            ]],
            'table' => [
                'columns' => ['Kategori', 'Laporan', 'Kelompok'],
                'numeric' => [1, 2],
                'rows' => $rows->map(fn (array $row) => [$row['label'], Format::number($row['reports']), Format::number($row['organizations'])])->all(),
            ],
            'summary' => $title.'. '.$rows->map(fn (array $row) => $row['label'].': '.$row['reports'].' laporan')->join('; ').'.',
            'empty' => $rows->isEmpty() ? 'Tidak ada '.mb_strtolower($title).' yang dicatat di laporan terverifikasi pada rentang ini.' : null,
            'height' => self::horizontalHeight($rows->count()),
        ];
    }

    /**
     * Tren produksi Kelompok. Produk dengan satuan sama berbagi satu grafik (sumbu Y bertuliskan satuannya);
     * satuan berbeda mendapat grafik kecil sendiri-sendiri, agar tidak ada satu sumbu untuk dua satuan.
     */
    public static function productionTrend(Collection $series, Collection $months): array
    {
        $name = fn (array $row) => $row['product'].($row['unit'] ? ' ('.$row['unit'].')' : '');
        $labels = self::monthLabels($months->map(fn (CarbonImmutable $month) => ['period' => $month]));
        $byUnit = $series->values()->groupBy(fn (array $row) => $row['unit'] ?? '');
        $split = $byUnit->count() > 1;

        $panels = $byUnit->map(fn (Collection $group, string $unit) => [
            'subtitle' => $split ? $name($group->first()) : null,
            'type' => 'line',
            'format' => 'number',
            'labels' => $labels,
            'yTitle' => $unit !== '' ? $unit : 'tanpa satuan',
            'legend' => ['position' => 'bottom', 'always' => true],
            'height' => $split ? 180 : 288,
            'datasets' => $group->values()->map(fn (array $row, int $index) => [
                'label' => $name($row),
                'tone' => $index === 0 ? 'brand' : 'ink',
                'data' => $row['values'],
            ])->all(),
            'summary' => 'Produksi per bulan. '.$group->map(fn (array $row) => $name($row).': '.$months->values()->map(fn (CarbonImmutable $month, int $index) => Format::month($month).' '
                .($row['values'][$index] === null ? 'tidak ada data' : Format::quantity($row['values'][$index])))->join(', '))->join('; ').'.',
        ])->values()->all();

        return [
            'panels' => $panels,
            'table' => [
                'columns' => ['Bulan', ...$series->map($name)->all()],
                'numeric' => range(1, max(1, $series->count())),
                'rows' => $months->values()->map(fn (CarbonImmutable $month, int $index) => [
                    Format::month($month),
                    ...$series->map(fn (array $row) => Format::quantity($row['values'][$index]))->all(),
                ])->all(),
            ],
            'footnote' => match (true) {
                $split => 'Satuan kedua produk berbeda, sehingga setiap produk punya grafik sendiri.',
                $series->count() > 1 => 'Dua produk dengan produksi terbesar, satuannya sama.',
                default => 'Produk dengan produksi terbesar.',
            },
            'empty' => $series->isEmpty() ? 'Belum ada laporan terverifikasi yang mengisi jumlah produksi pada rentang ini.' : null,
        ];
    }

    /** "Okt 2025": singkat agar 12 label muat di HP. */
    private static function monthLabels(Collection $rows): array
    {
        return $rows->map(fn (array $row) => $row['period']->translatedFormat('M Y'))->values()->all();
    }

    private static function horizontalHeight(int $rows): int
    {
        return max(160, $rows * 36 + 48);
    }
}
