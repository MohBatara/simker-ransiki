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

test('tanggal dengan jam dan jumlah desimal', function () {
    expect(Format::dateTime('2026-10-09 10:42:00'))->toBe('9 Oktober 2026, 10.42')
        ->and(Format::quantity('12.50'))->toBe('12,5')
        ->and(Format::quantity('1250.00'))->toBe('1.250')
        ->and(Format::quantity('0.00'))->toBe('0')
        ->and(Format::quantity(null))->toBe(Format::EMPTY);
});

test('lama menunggu dalam hari kalender', function () {
    Illuminate\Support\Carbon::setTestNow('2026-10-20 08:00:00');

    expect(Format::waitingSince('2026-10-20 07:00:00'))->toBe('Hari ini')
        ->and(Format::waitingSince('2026-10-19 23:00:00'))->toBe('1 hari')
        ->and(Format::waitingSince('2026-10-06 09:00:00'))->toBe('14 hari')
        ->and(Format::waitingSince(null))->toBe(Format::EMPTY);

    Illuminate\Support\Carbon::setTestNow();
});
