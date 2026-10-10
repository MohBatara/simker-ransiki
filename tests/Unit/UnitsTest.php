<?php

use App\Support\Units;

test('satuan dirapikan: huruf kecil, spasi berlebih dibuang, kosong menjadi NULL', function () {
    expect(Units::normalize('  Kg '))->toBe('kg')
        ->and(Units::normalize('Karung   50 KG'))->toBe('karung 50 kg')
        ->and(Units::normalize(''))->toBeNull()
        ->and(Units::normalize(null))->toBeNull()
        ->and(Units::normalize(['kg']))->toBe(['kg']);
});

test('daftar satuan umum dengan pilihan lainnya di akhir', function () {
    expect(array_keys(Units::options()))->toBe(['kg', 'ton', 'gram', 'liter', 'ekor', 'ikat', 'bungkus', 'karung', 'buah', 'lembar', 'lainnya']);
});
