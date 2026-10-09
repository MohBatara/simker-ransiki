<?php

namespace App\Console\Commands;

use App\Http\Controllers\Publik\SiteImageController;
use App\Models\BusinessSector;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\ImageVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Membuat varian WebP untuk foto yang diunggah sebelum fitur varian ada. Aman dijalankan berulang.
 */
#[Signature('simker:varian-foto')]
#[Description('Buat varian WebP untuk foto produk, foto sampul, foto Tentang, dan foto bidang usaha yang sudah ada')]
class GenerateImageVariants extends Command
{
    public function handle(): int
    {
        if (! ImageVariants::webpSupported()) {
            $this->warn('GD di server ini tidak mendukung WebP. Website tetap memakai JPEG.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach (Product::whereNotNull('photo_path')->get(['id', 'photo_path']) as $product) {
            $count += $this->generate(Product::PHOTO_DISK, $product->photo_path, ImageVariants::PRODUCT);
        }

        foreach (SiteSetting::PHOTO_SLOTS as $slot => $key) {
            $count += $this->generate(SiteImageController::DISK, SiteSetting::get($key), ImageVariants::WIDE, $slot === 'sampul' ? ImageVariants::HERO_BUDGET : []);
        }

        foreach (BusinessSector::whereNotNull('photo_path')->get(['id', 'photo_path']) as $sector) {
            $count += $this->generate(SiteImageController::DISK, $sector->photo_path, ImageVariants::WIDE);
        }

        $this->info("Varian dibuat untuk {$count} foto.");

        return self::SUCCESS;
    }

    private function generate(string $disk, ?string $jpegPath, array $widths, array $budgets = []): int
    {
        if (! $jpegPath || ! Storage::disk($disk)->exists($jpegPath)) {
            return 0;
        }

        ImageVariants::store($disk, Storage::disk($disk)->path($jpegPath), $jpegPath, $widths, $budgets, writeJpeg: false);

        return 1;
    }
}
