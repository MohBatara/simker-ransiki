<?php

use App\Support\BentoGrid;

/**
 * Menempatkan kartu seperti penempatan otomatis CSS grid (grid-auto-flow: row, tanpa dense) lalu mengembalikan
 * peta sel terisi. Kartu pertama = kartu utama (2×2 di 4 kolom, 2×2 selebar baris di 2 kolom).
 */
function placeBento(int $types, int $columns): array
{
    $items = [[2, 2]];
    for ($j = 0; $j < $types - 1; $j++) {
        $items[] = BentoGrid::span($j, $types - 1, $columns);
    }

    $grid = [];
    [$row, $col] = [0, 0];
    foreach ($items as [$width, $height]) {
        while (true) {
            if ($col + $width > $columns) {
                [$row, $col] = [$row + 1, 0];

                continue;
            }
            $free = true;
            for ($r = $row; $r < $row + $height; $r++) {
                for ($c = $col; $c < $col + $width; $c++) {
                    $free = $free && ! isset($grid[$r][$c]);
                }
            }
            if ($free) {
                break;
            }
            $col++;
        }
        for ($r = $row; $r < $row + $height; $r++) {
            for ($c = $col; $c < $col + $width; $c++) {
                $grid[$r][$c] = true;
            }
        }
        $col += $width;
    }

    return $grid;
}

test('grid bento selalu penuh rapi untuk 2 sampai 9 jenis di tablet dan desktop', function (int $types, int $columns) {
    $grid = placeBento($types, $columns);

    foreach ($grid as $row => $cells) {
        expect(count($cells))->toBe($columns, "{$types} jenis, {$columns} kolom: baris {$row} tidak penuh");
    }
})->with(range(2, 9))->with([2, 4]);

test('kelas kartu kecil sesuai rentangnya', function () {
    expect(BentoGrid::smallClasses(0, 1))->toBe('md:col-span-2 lg:col-span-2 lg:row-span-2')
        ->and(BentoGrid::smallClasses(0, 4))->toBe('lg:col-span-1')
        ->and(BentoGrid::smallClasses(4, 5))->toBe('md:col-span-2 lg:col-span-4')
        ->and(BentoGrid::smallClasses(7, 8))->toBe('lg:col-span-1');
});
