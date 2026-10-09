<?php

namespace App\Actions\Files;

use App\Models\Product;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satu foto per produk, selalu JPEG, ditambah versi kecil untuk daftar dan halaman publik.
 */
class StoreProductPhoto
{
    public function handle(Product $product, UploadedFile $file): void
    {
        $base = sprintf('products/%d/%s', $product->id, Str::uuid());
        $disk = Storage::disk(Product::PHOTO_DISK);

        $photo = ImageProcessor::process($file->getRealPath(), $file->getMimeType(), ImageProcessor::MAX_SIDE, 'jpeg');
        $thumbnail = ImageProcessor::process($file->getRealPath(), $file->getMimeType(), ImageProcessor::THUMBNAIL_SIDE, 'jpeg');

        $disk->put($base.'.jpg', $photo['contents']);
        $disk->put($base.Product::THUMBNAIL_SUFFIX.'.jpg', $thumbnail['contents']);

        $product->deletePhotoFiles();
        $product->forceFill(['photo_path' => $base.'.jpg'])->save();
    }
}
