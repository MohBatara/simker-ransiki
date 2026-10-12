<?php

use App\Support\Initials;

test('inisial avatar kelompok: dua kata pertama setelah kata jenis dibuang, atau dua huruf satu kata', function (string $name, string $initials) {
    expect(Initials::organization($name))->toBe($initials);
})->with([
    ['Kelompok Tani Kakao Harapan', 'KH'],
    ['KUB Cokelat Olahan Lembah Subur', 'CO'],
    ['BUMDes Maju Bersama', 'MB'],
    ['UMKM Bumbu Dapur dan Sambal Ikan', 'BD'],
    ['Kelompok Nelayan Muara Indah Jaya', 'MI'],
    ['KUB Rio', 'RI'],
    ['KDMP', 'KD'],
]);
