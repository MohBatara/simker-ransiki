<?php

use App\Support\Format;

test('angka dan rupiah memakai format Indonesia', function () {
    expect(Format::number(1250000))->toBe('1.250.000')
        ->and(Format::number(2.5, 1))->toBe('2,5')
        ->and(Format::rupiah(1250000))->toBe('Rp 1.250.000')
        ->and(Format::rupiah(-5000))->toBe('-Rp 5.000');
});

test('nol tetap nol dan kosong tidak diubah menjadi nol', function () {
    expect(Format::number(0))->toBe('0')
        ->and(Format::rupiah(0))->toBe('Rp 0')
        ->and(Format::number(null))->toBe(Format::EMPTY)
        ->and(Format::rupiah(null))->toBe(Format::EMPTY);
});

test('tanggal dan bulan memakai nama bulan Indonesia', function () {
    expect(Format::date('2026-10-09'))->toBe('9 Oktober 2026')
        ->and(Format::month('2026-09-01'))->toBe('September 2026')
        ->and(Format::date(null))->toBe(Format::EMPTY);
});
