<?php

// Membuat ulang contoh file impor: sail artisan tinker --execute="require base_path('storage/app/samples/make.php');"
use App\Support\Exports\Spreadsheet;
use App\Support\Imports\OrganizationImport;
use Spatie\SimpleExcel\SimpleExcelWriter;

$rows = [
    ['KUB Kakao Contoh Baru', 'KUB', 'Kampung Contoh 2', 'Perkebunan; Pengolahan Hasil', 'Pertanian', '2026-09', 'Ketua Contoh A', '0812 0000 0001', null, 'Jalan Contoh 1', 2020, 15, 4, 'Mengolah biji kakao fermentasi (contoh).'],
    ['Kelompok Nelayan Contoh Baru', 'Kelompok Perikanan', 'Kampung Contoh 5', 'Perikanan', 'Perikanan; Dinkopdag', null, 'Ketua Contoh B', null, null, null, null, null, null, 'Jumlah anggota belum diketahui, jadi dibiarkan kosong.'],
    ['UMKM Keripik Contoh Baru', 'umkm', '  kampung   contoh 3 ', 'UMKM', 'dinkopdag', '2026-10', null, null, 'contoh@example.com', null, 2022, 6, 2, null],
    ['KUB Contoh 01', 'KUB', 'Kampung Contoh 1', 'Perkebunan', null, null, null, null, null, null, null, null, null, 'Sudah ada di data contoh: peringatan.'],
    ['UMKM Keripik Contoh Baru', 'UMKM', 'Kampung Contoh 3', 'UMKM', null, null, null, null, null, null, null, null, null, 'Duplikat baris 4 di file ini: peringatan.'],
    ['Kelompok Sagu Contoh', 'Lainnya', 'Kampung Contoh 9', 'Pengolahan Hasil', null, null, null, null, null, null, null, null, null, 'Kampung tidak ada di data master: gagal.'],
    ['Kelompok Pinang Contoh', 'KUB', 'Kampung Contoh 6', null, null, null, null, null, null, null, null, null, null, 'Bidang usaha kosong: gagal.'],
    ['BUMDes Contoh Baru', 'BUMDes', 'Kampung Contoh 7', 'Perdagangan', 'DPMPTSP', null, null, null, null, null, 2018, 0, null, 'Jumlah anggota 0 tetap tersimpan sebagai 0.'],
];

copy(Spreadsheet::write([['name' => 'Data', 'header' => OrganizationImport::headers(), 'rows' => $rows]]), storage_path('app/samples/contoh-impor-kelompok.xlsx'));

$csv = SimpleExcelWriter::create(storage_path('app/samples/contoh-impor-kelompok.csv'), delimiter: ';')->noHeaderRow();
$csv->addRow(OrganizationImport::headers());
foreach ($rows as $row) {
    $csv->addRow($row);
}
$csv->close();

echo "Contoh file impor dibuat.\n";
