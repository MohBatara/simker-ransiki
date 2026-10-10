<?php

use App\Support\NumberInput;

test('jumlah desimal: koma desimal, titik ribuan hanya bila diikuti tepat 3 digit', function (string $input, string $expected) {
    expect(NumberInput::decimal($input))->toBe($expected);
})->with([
    ['12.5', '12.5'],
    ['12,5', '12.5'],
    ['1.250', '1250'],
    ['1.250.000', '1250000'],
    ['1.250,5', '1250.5'],
    ['0.250', '0.250'],
    ['12.50', '12.50'],
    [' 40 ', '40'],
]);

test('jumlah desimal yang tidak terbaca dikembalikan apa adanya agar ditolak validasi', function (string $input) {
    expect(NumberInput::decimal($input))->toBe($input);
})->with(['abc', '12,', ',5', '1.25,5', '1.250.5', '12kg']);

test('uang dan jumlah bulat: titik, koma, dan Rp dibuang', function (string $input, int $expected) {
    expect(NumberInput::integer($input))->toBe($expected);
})->with([
    ['1.250.000', 1250000],
    ['1,250,000', 1250000],
    ['Rp 1.250.000', 1250000],
    ['12,5', 125],
    ['-3', -3],
    ['0', 0],
    ['007', 7],
]);

test('teks kosong menjadi NULL, nol tetap nol', function () {
    expect(NumberInput::integer(''))->toBeNull()
        ->and(NumberInput::integer('   '))->toBeNull()
        ->and(NumberInput::decimal(''))->toBeNull()
        ->and(NumberInput::integer('0'))->toBe(0)
        ->and(NumberInput::decimal('0'))->toBe('0')
        ->and(NumberInput::integer(null))->toBeNull();
});

test('nilai tersimpan ditampilkan kembali dalam format yang terbaca sama', function () {
    expect(NumberInput::display(1250000))->toBe('1.250.000')
        ->and(NumberInput::display('1250.50', true))->toBe('1.250,5')
        ->and(NumberInput::display('10.00', true))->toBe('10')
        ->and(NumberInput::display('0.25', true))->toBe('0,25')
        ->and(NumberInput::display(null))->toBe('')
        ->and(NumberInput::decimal(NumberInput::display('1250.50', true)))->toBe('1250.5');
});
