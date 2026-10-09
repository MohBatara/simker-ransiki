<?php

namespace App\Actions\Files;

use App\Models\Product;
use App\Support\ImageProcessor;
use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satu foto per produk: JPEG 1600 + JPEG kecil 400 (daftar) + WebP 400/800 (website publik).
 */
class StoreProductPhoto
{
    public function handle(Product $product, UploadedFile $file): void
    {
        $base = sprintf('products/%d/%s', $product->id, Str::uuid());
        $disk = Storage::disk(Product::PHOTO_DISK);

        // JPEG 1600 + WebP 400/800 untuk srcset, ditambah JPEG 400 sebagai cadangan kartu.
        ImageVariants::store(Product::PHOTO_DISK, $file->getRealPath(), $base.'.jpg', ImageVariants::PRODUCT);
        $thumbnail = ImageProcessor::process($file->getRealPath(), $file->getMimeType(), ImageProcessor::THUMBNAIL_SIDE, 'jpeg');
        $disk->put($base.Product::THUMBNAIL_SUFFIX.'.jpg', $thumbnail['contents']);

        $product->deletePhotoFiles();
        $product->forceFill(['photo_path' => $base.'.jpg'])->save();
    }
}
