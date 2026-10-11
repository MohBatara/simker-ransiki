<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * File unggahan sungguhan di folder sementara. Berbeda dengan UploadedFile::fake(), tipe file-nya
 * dibaca dari isi (finfo), sehingga pengecekan tipe berdasarkan isi benar-benar teruji.
 */
function realUpload(string $name, string $contents): Illuminate\Http\UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'unggah');
    file_put_contents($path, $contents);

    return new Illuminate\Http\UploadedFile($path, $name, null, null, true);
}

function imageBytes(int $width, int $height, string $format = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
    ob_start();
    $format === 'png' ? imagepng($image) : imagejpeg($image, null, 90);

    return ob_get_clean();
}

/**
 * JPEG dengan segmen EXIF berisi orientasi dan lokasi GPS, seperti foto dari HP.
 * TIFF little-endian: IFD0 (Orientation, penunjuk GPS) di offset 8, IFD GPS di 38, nilai lintang di 68.
 */
function jpegWithExif(int $width, int $height, int $orientation = 6): string
{
    $entry = fn (int $tag, int $type, int $count, string $value) => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\0");

    $tiff = 'II'.pack('vV', 42, 8)
        .pack('v', 2).$entry(0x0112, 3, 1, pack('v', $orientation)).$entry(0x8825, 4, 1, pack('V', 38)).pack('V', 0)
        .pack('v', 2).$entry(0x0001, 2, 2, "S\0").$entry(0x0002, 5, 3, pack('V', 68)).pack('V', 0)
        .pack('V6', 1, 1, 30, 1, 0, 1);
    $app1 = "Exif\0\0".$tiff;
    $jpeg = imageBytes($width, $height);

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

/** Data EXIF dari isi file gambar; array kosong bila tidak ada. */
function exifOf(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'exif');
    file_put_contents($path, $contents);

    return @exif_read_data($path) ?: [];
}

/** Semua nilai href di HTML, sudah di-decode seperti yang dibaca browser. */
function hrefsIn(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

    return collect($document->getElementsByTagName('a'))->map(fn (DOMElement $a) => $a->getAttribute('href'))->all();
}
