<?php

use App\Support\SimilarNames;

test('nama dianggap mirip', function (string $first, string $second) {
    expect(SimilarNames::areSimilar($first, $second))->toBeTrue();
})->with([
    'singkatan kel.' => ['Kelompok Tani Maju', 'Kel. Tani Maju'],
    'beda huruf besar dan spasi' => ['KUB  Sagu Lestari', 'kub sagu lestari'],
    'angka sama ditulis berbeda' => ['KUB Contoh 01', 'KUB Contoh 1'],
    'salah ketik pada nama panjang' => ['Kelompok Tani Harapan Jaya', 'Kelompok Tani Harapan Jaja'],
    'awalan umum berbeda' => ['Koperasi Mina Bahari', 'KUB Mina Bahari'],
]);

test('nama dianggap tidak mirip', function (string $first, string $second) {
    expect(SimilarNames::areSimilar($first, $second))->toBeFalse();
})->with([
    'angka berbeda' => ['KUB Contoh 01', 'KUB Contoh 02'],
    'penanda tani dan nelayan berbeda' => ['Kelompok Tani Sejahtera', 'Kelompok Nelayan Sejahtera'],
    'nama pendek beda satu huruf' => ['UMKM Sagu', 'UMKM Sagi'],
    'nama berbeda' => ['Kelompok Maju', 'Kelompok Mundur'],
]);
